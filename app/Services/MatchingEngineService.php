<?php

namespace App\Services;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Helpers\TradingConfig;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MatchingEngineService
{
    /**
     * Attempt to match an order against the orderbook.
     * This method should be called after order creation.
     */
    public function matchOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Lock the incoming order for update
            $order = Order::lockForUpdate()->find($order->id);

            if (!$order || !$order->isOpen()) {
                return;
            }

            $remainingAmount = $this->getRemainingAmount($order);

            if (bccomp((string) $remainingAmount, '0', 8) <= 0) {
                return;
            }

            // Find matching orders on the opposite side
            $matchingOrders = $this->findMatchingOrders($order);

            foreach ($matchingOrders as $counterOrder) {
                // Re-check remaining after each match
                $remainingAmount = $this->getRemainingAmount($order);
                if (bccomp((string) $remainingAmount, '0', 8) <= 0) {
                    break;
                }

                // Lock counter order for update
                $counterOrder = Order::lockForUpdate()->find($counterOrder->id);

                // Skip if order is no longer open or available
                if (!$counterOrder || !$counterOrder->isOpen()) {
                    continue;
                }

                $counterRemaining = $this->getRemainingAmount($counterOrder);
                if (bccomp((string) $counterRemaining, '0', 8) <= 0) {
                    continue;
                }

                // Execute the match
                $this->executeMatch($order, $counterOrder, $remainingAmount, $counterRemaining);

                // Refresh order to get updated filled_amount
                $order->refresh();
            }
        });
    }

    /**
     * Get remaining unfilled amount for an order.
     */
    private function getRemainingAmount(Order $order): string
    {
        $filledAmount = $order->filled_amount ?? '0';
        return bcsub((string) $order->amount, (string) $filledAmount, 8);
    }

    /**
     * Find matching orders for the given order.
     * For buy orders: find open sell orders with price <= buy price
     * For sell orders: find open buy orders with price >= sell price
     * Sorted by price priority and then by time (FIFO)
     */
    private function findMatchingOrders(Order $order)
    {
        $query = Order::query()
            ->open()
            ->forSymbol($order->symbol)
            ->where('user_id', '!=', $order->user_id);

        if ($order->side === OrderSide::BUY) {
            // For buy orders, match sell orders where sell_price <= buy_price
            return $query
                ->sellSide()
                ->where('price', '<=', $order->price)
                ->orderBy('price', 'asc')      // Best price first (lowest for sells)
                ->orderBy('created_at', 'asc') // FIFO
                ->get();
        } else {
            // For sell orders, match buy orders where buy_price >= sell_price
            return $query
                ->buySide()
                ->where('price', '>=', $order->price)
                ->orderBy('price', 'desc')     // Best price first (highest for buys)
                ->orderBy('created_at', 'asc') // FIFO
                ->get();
        }
    }

    /**
     * Execute a match between two orders.
     */
    private function executeMatch(
        Order $incomingOrder,
        Order $counterOrder,
        string $incomingRemaining,
        string $counterRemaining
    ): void {
        // Determine match amount (minimum of both remaining amounts)
        $matchAmount = bccomp($incomingRemaining, $counterRemaining, 8) <= 0
            ? $incomingRemaining
            : $counterRemaining;

        // Use maker's price (counter order is the maker since it was placed first)
        $matchPrice = (string) $counterOrder->price;

        // Calculate trade value in USD
        $tradeValue = bcmul($matchPrice, $matchAmount, 8);

        // Calculate commission
        $commission = TradingConfig::calculateCommission((float) $tradeValue);

        // Determine which order is buy and which is sell
        if ($incomingOrder->side === OrderSide::BUY) {
            $buyOrder = $incomingOrder;
            $sellOrder = $counterOrder;
        } else {
            $buyOrder = $counterOrder;
            $sellOrder = $incomingOrder;
        }

        // Create trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $incomingOrder->symbol,
            'price' => $matchPrice,
            'amount' => $matchAmount,
            'commission' => $commission,
        ]);

        // Update filled amounts and statuses
        $this->updateOrderFill($incomingOrder, $matchAmount);
        $this->updateOrderFill($counterOrder, $matchAmount);

        // Process balance and asset transfers
        $this->processTransfers($buyOrder, $sellOrder, $matchAmount, $matchPrice, $commission);

        // Dispatch OrderMatched event for real-time broadcasting
        event(new OrderMatched($trade, $buyOrder->user_id, $sellOrder->user_id));
    }

    /**
     * Update order's filled amount and status.
     */
    private function updateOrderFill(Order $order, string $amount): void
    {
        $newFilledAmount = bcadd((string) ($order->filled_amount ?? '0'), $amount, 8);
        $order->filled_amount = $newFilledAmount;

        // Check if fully filled
        if (bccomp($newFilledAmount, (string) $order->amount, 8) >= 0) {
            $order->status = OrderStatus::FILLED;
            $order->filled_at = now();
        }

        $order->save();
    }

    /**
     * Process balance and asset transfers between buyer and seller.
     */
    private function processTransfers(
        Order $buyOrder,
        Order $sellOrder,
        string $matchAmount,
        string $matchPrice,
        float $commission
    ): void {
        $tradeValue = bcmul($matchPrice, $matchAmount, 8);
        $commissionFrom = TradingConfig::commissionFrom();

        // Lock users for update
        $buyer = User::lockForUpdate()->find($buyOrder->user_id);
        $seller = User::lockForUpdate()->find($sellOrder->user_id);

        // Lock seller's asset for update
        $sellerAsset = Asset::lockForUpdate()
            ->where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->first();

        // Deduct locked amount from seller's asset
        $sellerAsset->deductLockedAmount((float) $matchAmount);

        // Credit buyer with asset (potentially minus commission)
        $buyerAsset = $buyer->getOrCreateAsset($sellOrder->symbol);
        $buyerAsset = Asset::lockForUpdate()->find($buyerAsset->id);

        if ($commissionFrom === 'buyer') {
            // Commission deducted from asset received
            $assetCommission = bcdiv((string) $commission, $matchPrice, 8);
            $buyerReceives = bcsub($matchAmount, $assetCommission, 8);
            $buyerAsset->addAmount((float) $buyerReceives);

            // Seller receives full USD
            $seller->addBalance((float) $tradeValue);
        } else {
            // Buyer receives full asset
            $buyerAsset->addAmount((float) $matchAmount);

            // Commission deducted from USD received by seller
            $sellerReceives = bcsub($tradeValue, (string) $commission, 8);
            $seller->addBalance((float) $sellerReceives);
        }

        // Note: Buyer's USD was already deducted when placing the buy order
        // If buyer paid more than match price, refund the difference
        $buyerPaid = bcmul((string) $buyOrder->price, $matchAmount, 8);
        $refund = bcsub($buyerPaid, $tradeValue, 8);

        if (bccomp($refund, '0', 8) > 0) {
            $buyer->addBalance((float) $refund);
        }
    }
}

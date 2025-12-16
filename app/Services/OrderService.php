<?php

namespace App\Services;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Helpers\TradingConfig;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected MatchingEngineService $matchingEngine
    ) {}

    /**
     * Place a buy order.
     *
     * @throws ValidationException
     */
    public function placeBuyOrder(User $user, string $symbol, float $price, float $amount): Order
    {
        $order = DB::transaction(function () use ($user, $symbol, $price, $amount) {
            // Lock user for update to prevent race conditions
            $user = User::lockForUpdate()->find($user->id);

            $orderTotal = bcmul((string) $price, (string) $amount, 8);
            
            // Check if user has enough balance
            if (! $user->hasEnoughBalance((float) $orderTotal)) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance',
                ])->withMessages(['message' => 'Insufficient balance']);
            }

            // Deduct balance
            $user->deductBalance((float) $orderTotal);

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => strtoupper($symbol),
                'side' => OrderSide::BUY,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);

            return $order;
        });

        // Attempt to match the order
        $this->matchingEngine->matchOrder($order);

        return $order->fresh();
    }

    /**
     * Place a sell order.
     *
     * @throws ValidationException
     */
    public function placeSellOrder(User $user, string $symbol, float $price, float $amount): Order
    {
        $order = DB::transaction(function () use ($user, $symbol, $price, $amount) {
            $symbol = strtoupper($symbol);

            // Get or create asset (locked for update)
            $asset = Asset::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('symbol', $symbol)
                ->first();

            if (! $asset) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient assets',
                ])->withMessages(['message' => 'Insufficient assets']);
            }

            // Check if user has enough assets
            if (! $asset->hasEnoughAmount($amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient assets',
                ])->withMessages(['message' => 'Insufficient assets']);
            }

            // Lock the asset amount
            $asset->lockAmount($amount);

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => OrderSide::SELL,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);

            return $order;
        });

        // Attempt to match the order
        $this->matchingEngine->matchOrder($order);

        return $order->fresh();
    }

    /**
     * Cancel an order.
     *
     * @throws ValidationException
     */
    public function cancelOrder(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user) {
            // Check authorization
            if ($order->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'order' => 'Unauthorized to cancel this order',
                ])->withMessages(['message' => 'Unauthorized to cancel this order']);
            }

            // Check if order is already filled
            if ($order->isFilled()) {
                throw ValidationException::withMessages([
                    'order' => 'Cannot cancel a filled order',
                ])->withMessages(['message' => 'Cannot cancel a filled order']);
            }

            // Check if already cancelled
            if ($order->isCancelled()) {
                throw ValidationException::withMessages([
                    'order' => 'Order is already cancelled',
                ])->withMessages(['message' => 'Order is already cancelled']);
            }

            // Lock order for update
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->side === OrderSide::BUY) {
                // Release locked USD
                $orderTotal = bcmul((string) $order->price, (string) $order->amount, 8);
                $user->addBalance((float) $orderTotal);
            } else {
                // Release locked assets
                $asset = Asset::lockForUpdate()
                    ->where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->firstOrFail();

                $asset->releaseAmount((float) $order->amount);
            }

            // Mark order as cancelled
            $order->markCancelled();

            return $order;
        });
    }

    /**
     * Get orderbook for a symbol.
     */
    public function getOrderbook(string $symbol, ?string $side = null): Collection
    {
        $query = Order::query()
            ->open()
            ->forSymbol($symbol);

        if ($side === 'buy') {
            return $query->buySide()
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();
        }

        if ($side === 'sell') {
            return $query->sellSide()
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
        }

        // Return both buy and sell if no side specified
        $buy = $query->clone()->buySide()
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();
        $sell = $query->clone()->sellSide()
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

        return $buy->merge($sell);
    }
}

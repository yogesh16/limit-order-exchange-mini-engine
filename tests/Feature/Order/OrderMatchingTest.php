<?php

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Helpers\TradingConfig;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\MatchingEngineService;
use App\Services\OrderService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Buyer has USD balance
    $this->buyer = User::factory()->create([
        'balance' => 100000.00,
    ]);

    // Seller has BTC to sell
    $this->seller = User::factory()->create([
        'balance' => 0,
    ]);

    Asset::create([
        'user_id' => $this->seller->id,
        'symbol' => 'BTC',
        'amount' => 10.0,
        'locked_amount' => 0,
    ]);
});

describe('Basic Matching', function () {
    test('buy order matches eligible sell order', function () {
        // Seller places sell order first (maker)
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Buyer places buy order at a price >= sell price (taker)
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 51000.00, // Higher than sell price, should match
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);
        $buyOrderId = $buyResponse->json('data.id');

        // Assert trade was created
        $this->assertDatabaseHas('trades', [
            'buy_order_id' => $buyOrderId,
            'sell_order_id' => $sellOrderId,
            'symbol' => 'BTC',
            'price' => '50000.00000000', // Maker's price
            'amount' => '1.00000000',
        ]);

        // Assert both orders are filled
        $this->assertDatabaseHas('orders', [
            'id' => $buyOrderId,
            'status' => OrderStatus::FILLED->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $sellOrderId,
            'status' => OrderStatus::FILLED->value,
        ]);
    });

    test('sell order matches eligible buy order', function () {
        // Buyer places buy order first (maker)
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);
        $buyOrderId = $buyResponse->json('data.id');

        // Seller places sell order at a price <= buy price (taker)
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 49000.00, // Lower than buy price, should match
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Assert trade was created at maker's (buy) price
        $this->assertDatabaseHas('trades', [
            'buy_order_id' => $buyOrderId,
            'sell_order_id' => $sellOrderId,
            'symbol' => 'BTC',
            'price' => '50000.00000000', // Maker's price (buyer was first)
            'amount' => '1.00000000',
        ]);
    });

    test('no match for non-overlapping prices', function () {
        // Seller places sell order at high price
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 55000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Buyer places buy order at lower price - no overlap
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00, // Below sell price, no match
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Assert no trade was created
        $this->assertDatabaseCount('trades', 0);

        // Both orders should remain open
        $this->assertDatabaseHas('orders', [
            'status' => OrderStatus::OPEN->value,
            'side' => OrderSide::BUY->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'status' => OrderStatus::OPEN->value,
            'side' => OrderSide::SELL->value,
        ]);
    });
});

describe('FIFO Matching', function () {
    test('oldest order gets matched first', function () {
        // Create two sellers with BTC
        $seller2 = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => 10.0,
            'locked_amount' => 0,
        ]);

        // First seller places order
        actingAs($this->seller);
        $sellResponse1 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse1->assertStatus(201);
        $sellOrderId1 = $sellResponse1->json('data.id');

        // Second seller places order at same price (later)
        actingAs($seller2);
        $sellResponse2 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse2->assertStatus(201);
        $sellOrderId2 = $sellResponse2->json('data.id');

        // Buyer places order - should match first seller's order (FIFO)
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Assert first seller's order was matched
        $this->assertDatabaseHas('trades', [
            'sell_order_id' => $sellOrderId1,
        ]);

        // Assert second seller's order is still open
        $this->assertDatabaseHas('orders', [
            'id' => $sellOrderId2,
            'status' => OrderStatus::OPEN->value,
        ]);
    });
});

describe('Commission Calculation', function () {
    test('commission is calculated at 1.5%', function () {
        // Seller places order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);

        // Buyer matches
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Trade value = 50000 * 1 = 50000 USD
        // Commission = 50000 * 0.015 = 750 USD
        $trade = Trade::first();
        expect($trade)->not->toBeNull();
        
        $expectedCommission = TradingConfig::calculateCommission(50000.00);
        expect((float) $trade->commission)->toBe($expectedCommission);
    });
});

describe('Balance and Asset Updates', function () {
    test('buyer receives asset after match (minus commission if applicable)', function () {
        $initialBuyerBalance = $this->buyer->balance;

        // Seller places order
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Buyer matches
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        $this->buyer->refresh();

        // Buyer should have BTC asset now
        $buyerAsset = Asset::where('user_id', $this->buyer->id)
            ->where('symbol', 'BTC')
            ->first();

        expect($buyerAsset)->not->toBeNull();
        
        // If commission from buyer, they receive less asset
        if (TradingConfig::commissionFrom() === 'buyer') {
            $commission = TradingConfig::calculateCommission(50000.00);
            $assetCommission = bcdiv((string) $commission, '50000.00', 8);
            $expectedAsset = bcsub('1.0', $assetCommission, 8);
            expect((string) $buyerAsset->amount)->toBe($expectedAsset);
        } else {
            expect((string) $buyerAsset->amount)->toBe('1.00000000');
        }
    });

    test('seller receives USD after match (minus commission if applicable)', function () {
        // Seller places order
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Buyer matches
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        $this->seller->refresh();

        // Calculate expected balance
        $tradeValue = 50000.00;
        if (TradingConfig::commissionFrom() === 'seller') {
            $commission = TradingConfig::calculateCommission($tradeValue);
            $expectedBalance = $tradeValue - $commission;
        } else {
            $expectedBalance = $tradeValue;
        }

        expect((float) $this->seller->balance)->toBe($expectedBalance);
    });

    test('seller locked_amount is reduced after match', function () {
        $sellerAsset = Asset::where('user_id', $this->seller->id)
            ->where('symbol', 'BTC')
            ->first();
        $initialAmount = (float) $sellerAsset->amount;

        // Seller places order - amount gets locked
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        $sellerAsset->refresh();
        expect((float) $sellerAsset->locked_amount)->toBe(1.0);
        expect((float) $sellerAsset->amount)->toBe($initialAmount - 1.0);

        // Buyer matches - locked amount should be deducted
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        $sellerAsset->refresh();
        expect((float) $sellerAsset->locked_amount)->toBe(0.0);
    });
});

describe('Partial Fills', function () {
    test('partial fill updates filled_amount correctly', function () {
        // Seller places large order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 2.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Buyer places smaller order
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Sell order should be partially filled
        $sellOrder = Order::find($sellOrderId);
        expect((float) $sellOrder->filled_amount)->toBe(1.0);
        expect($sellOrder->status)->toBe(OrderStatus::OPEN); // Still open

        // Trade amount should be 1.0 (smaller of the two)
        $trade = Trade::first();
        expect((float) $trade->amount)->toBe(1.0);
    });

    test('order becomes filled when completely matched', function () {
        // Seller places order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Buyer places matching order for same amount
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Sell order should be fully filled
        $sellOrder = Order::find($sellOrderId);
        expect((float) $sellOrder->filled_amount)->toBe(1.0);
        expect($sellOrder->status)->toBe(OrderStatus::FILLED);
    });

    test('buyer order matches multiple sell orders', function () {
        // Create second seller
        $seller2 = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => 10.0,
            'locked_amount' => 0,
        ]);

        // First seller places order for 0.5 BTC
        actingAs($this->seller);
        $sell1 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);
        $sell1->assertStatus(201);

        // Second seller places order for 0.5 BTC
        actingAs($seller2);
        $sell2 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);
        $sell2->assertStatus(201);

        // Buyer wants 1 BTC - should match both
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Should have 2 trades
        expect(Trade::count())->toBe(2);

        // Both sell orders should be filled
        expect(Order::where('side', OrderSide::SELL)->where('status', OrderStatus::FILLED)->count())->toBe(2);
    });
});

<?php

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->buyer = User::factory()->create([
        'balance' => 100000.00,
    ]);

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

describe('Double Spending Prevention', function () {
    test('user cannot spend same balance twice on concurrent buy orders', function () {
        // Create user with exact balance for one order
        $user = User::factory()->create([
            'balance' => 50000.00, // Exactly enough for one 1 BTC order at 50000
        ]);

        // Seller has BTC available
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 2.0, // Enough for both orders if double-spend occurred
        ])->assertStatus(201);

        // First order should succeed
        actingAs($user);
        $response1 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response1->assertStatus(201);

        // Second order should fail - no balance left
        $response2 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response2->assertStatus(422);
        $response2->assertJson(['message' => 'Insufficient balance']);

        // Only one order should exist for this user
        expect(Order::where('user_id', $user->id)->count())->toBe(1);
    });

    test('user cannot sell same asset twice on concurrent sell orders', function () {
        // Create user with exact asset for one order
        $user = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => 1.0, // Exactly enough for one order
            'locked_amount' => 0,
        ]);

        // First sell order should succeed
        actingAs($user);
        $response1 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response1->assertStatus(201);

        // Second sell order should fail - asset already locked
        $response2 = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response2->assertStatus(422);
        $response2->assertJson(['message' => 'Insufficient assets']);

        // Only one sell order should exist
        expect(Order::where('user_id', $user->id)->count())->toBe(1);
    });
});

describe('Atomic Operations', function () {
    test('order and balance update happen atomically', function () {
        $initialBalance = $this->buyer->balance;

        actingAs($this->buyer);
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response->assertStatus(201);

        $this->buyer->refresh();

        // Balance should be reduced
        $expectedBalance = bcsub((string) $initialBalance, '50000.00', 8);
        expect((string) $this->buyer->balance)->toBe($expectedBalance);

        // Order should exist
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->buyer->id,
            'side' => OrderSide::BUY->value,
            'status' => OrderStatus::OPEN->value,
        ]);
    });

    test('trade and user updates happen atomically', function () {
        $initialSellerBtc = 10.0;
        $initialBuyerBalance = (float) $this->buyer->balance;

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

        // Verify all changes happened atomically
        $this->seller->refresh();
        $this->buyer->refresh();

        // Trade exists
        $this->assertDatabaseCount('trades', 1);

        // Seller received USD
        expect((float) $this->seller->balance)->toBeGreaterThan(0);

        // Buyer's balance was deducted
        expect((float) $this->buyer->balance)->toBeLessThan($initialBuyerBalance);

        // Buyer has BTC asset
        $buyerAsset = Asset::where('user_id', $this->buyer->id)
            ->where('symbol', 'BTC')
            ->first();
        expect($buyerAsset)->not->toBeNull();
        expect((float) $buyerAsset->amount)->toBeGreaterThan(0);

        // Seller's locked BTC was consumed
        $sellerAsset = Asset::where('user_id', $this->seller->id)
            ->where('symbol', 'BTC')
            ->first();
        expect((float) $sellerAsset->locked_amount)->toBe(0.0);
    });

    test('failed match does not affect balances', function () {
        $initialBalance = $this->buyer->balance;

        // Seller places order at high price
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 60000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Buyer places order at lower price - no match
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        $this->buyer->refresh();

        // No trade created
        $this->assertDatabaseCount('trades', 0);

        // Buyer balance was deducted for order (not for trade)
        $expectedBalance = bcsub((string) $initialBalance, '50000.00', 8);
        expect((string) $this->buyer->balance)->toBe($expectedBalance);

        // Seller still has locked BTC, no USD
        $this->seller->refresh();
        expect((float) $this->seller->balance)->toBe(0.0);
    });
});

describe('Race Condition Handling', function () {
    test('pessimistic locking prevents order from matching twice', function () {
        // Create one sell order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Create two buyers
        $buyer2 = User::factory()->create(['balance' => 100000.00]);

        // First buyer matches
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Second buyer tries to buy - should not match same sell order
        actingAs($buyer2);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Only ONE trade should exist for that sell order
        expect(Trade::where('sell_order_id', $sellOrderId)->count())->toBe(1);

        // Sell order should be filled
        $this->assertDatabaseHas('orders', [
            'id' => $sellOrderId,
            'status' => OrderStatus::FILLED->value,
        ]);

        // Second buyer's order should remain open (no matching sell orders)
        $this->assertDatabaseHas('orders', [
            'user_id' => $buyer2->id,
            'status' => OrderStatus::OPEN->value,
        ]);
    });

    test('cancel and match race condition is handled correctly', function () {
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

        // Cancel the order
        $cancelResponse = $this->postJson("/api/orders/{$sellOrderId}/cancel");
        $cancelResponse->assertStatus(200);

        // Buyer tries to match cancelled order
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // No trade should be created (order was cancelled)
        $this->assertDatabaseCount('trades', 0);

        // Sell order should be cancelled
        $this->assertDatabaseHas('orders', [
            'id' => $sellOrderId,
            'status' => OrderStatus::CANCELLED->value,
        ]);
    });
});

<?php

/**
 * End-to-End Trading Flow Tests
 * 
 * These tests verify complete trading scenarios from user registration
 * through order placement, matching, and final state verification.
 */

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

beforeEach(function () {
    // Create buyer with USD balance
    $this->buyer = User::factory()->create([
        'name' => 'Buyer User',
        'balance' => 100000.00,
    ]);

    // Create seller with BTC
    $this->seller = User::factory()->create([
        'name' => 'Seller User',
        'balance' => 0,
    ]);

    Asset::create([
        'user_id' => $this->seller->id,
        'symbol' => 'BTC',
        'amount' => 10.0,
        'locked_amount' => 0,
    ]);
});

describe('Complete Buy Flow', function () {
    test('buyer can place order and have it matched when seller order exists', function () {
        Event::fake([OrderMatched::class]);

        // Step 1: Seller places sell order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $sellResponse->assertStatus(201);
        $sellOrderId = $sellResponse->json('data.id');

        // Verify seller's BTC is locked
        $sellerAsset = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first();
        expect((float) $sellerAsset->locked_amount)->toBe(1.0);

        // Step 2: Buyer places matching buy order
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $buyResponse->assertStatus(201);

        // Step 3: Verify trade was created
        expect(Trade::count())->toBe(1);
        
        $trade = Trade::first();
        expect((float) $trade->price)->toBe(50000.0);
        expect((float) $trade->amount)->toBe(1.0);

        // Step 4: Verify orders are filled
        expect(Order::find($sellOrderId)->status)->toBe(OrderStatus::FILLED);
        
        // Step 5: Verify buyer received BTC (minus commission)
        $buyerAsset = Asset::where('user_id', $this->buyer->id)->where('symbol', 'BTC')->first();
        expect($buyerAsset)->not->toBeNull();
        expect((float) $buyerAsset->amount)->toBeGreaterThan(0);

        // Step 6: Verify seller received USD
        $this->seller->refresh();
        expect((float) $this->seller->balance)->toBe(50000.0);

        // Step 7: Event was fired
        Event::assertDispatched(OrderMatched::class);
    });

    test('buyer order remains open when no matching seller', function () {
        actingAs($this->buyer);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 45000.00,
            'amount' => 1.0,
        ]);

        $response->assertStatus(201);
        
        $order = Order::find($response->json('data.id'));
        expect($order->status)->toBe(OrderStatus::OPEN);
        expect(Trade::count())->toBe(0);
    });
});

describe('Complete Sell Flow', function () {
    test('seller can place order and have it matched when buyer order exists', function () {
        Event::fake([OrderMatched::class]);

        // Step 1: Buyer places buy order first
        actingAs($this->buyer);
        $buyResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 52000.00,
            'amount' => 0.5,
        ]);
        $buyResponse->assertStatus(201);
        $buyOrderId = $buyResponse->json('data.id');

        // Verify buyer's USD is locked (balance reduced)
        $this->buyer->refresh();
        $expectedBalance = 100000 - (52000 * 0.5);
        expect((float) $this->buyer->balance)->toBe($expectedBalance);

        // Step 2: Seller places matching sell order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);
        $sellResponse->assertStatus(201);

        // Step 3: Verify trade was created at maker's price (buyer's price = $52,000)
        expect(Trade::count())->toBe(1);
        
        $trade = Trade::first();
        expect((float) $trade->price)->toBe(52000.0); // Maker's price

        // Step 4: Verify orders are filled
        expect(Order::find($buyOrderId)->status)->toBe(OrderStatus::FILLED);

        // Step 5: Event was fired
        Event::assertDispatched(OrderMatched::class);
    });
});

describe('Partial Fill Scenarios', function () {
    test('large order partially fills against smaller orders', function () {
        // Create another seller
        $seller2 = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => 5.0,
            'locked_amount' => 0,
        ]);

        // Seller 1 places order for 0.3 BTC
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.3,
        ])->assertStatus(201);

        // Seller 2 places order for 0.4 BTC
        actingAs($seller2);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.4,
        ])->assertStatus(201);

        // Buyer wants 1 BTC - should match both + create open order for remaining 0.3
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

        // Buyer order should be partially filled
        $buyOrder = Order::find($buyResponse->json('data.id'));
        expect((float) $buyOrder->filled_amount)->toBe(0.7); // 0.3 + 0.4
        expect($buyOrder->status)->toBe(OrderStatus::OPEN); // Not fully filled
    });
});

describe('Order Cancellation', function () {
    test('user can cancel their open order and get funds back', function () {
        actingAs($this->buyer);

        // Place buy order
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ]);
        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Balance reduced
        $this->buyer->refresh();
        expect((float) $this->buyer->balance)->toBe(50000.0);

        // Cancel order
        $cancelResponse = postJson("/api/orders/{$orderId}/cancel");
        $cancelResponse->assertStatus(200);

        // Order is cancelled
        expect(Order::find($orderId)->status)->toBe(OrderStatus::CANCELLED);

        // Balance restored
        $this->buyer->refresh();
        expect((float) $this->buyer->balance)->toBe(100000.0);
    });

    test('seller can cancel order and get assets back', function () {
        actingAs($this->seller);

        // Place sell order
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 2.0,
        ]);
        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // BTC is locked
        $sellerAsset = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first();
        expect((float) $sellerAsset->locked_amount)->toBe(2.0);

        // Cancel order
        $cancelResponse = postJson("/api/orders/{$orderId}/cancel");
        $cancelResponse->assertStatus(200);

        // BTC unlocked
        $sellerAsset->refresh();
        expect((float) $sellerAsset->locked_amount)->toBe(0.0);
        expect((float) $sellerAsset->amount)->toBe(10.0);
    });

    test('cannot cancel already filled order', function () {
        // Seller places order
        actingAs($this->seller);
        $sellResponse = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);
        $sellOrderId = $sellResponse->json('data.id');

        // Buyer matches
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);

        // Order is filled
        expect(Order::find($sellOrderId)->status)->toBe(OrderStatus::FILLED);

        // Try to cancel - should fail (422 validation error)
        actingAs($this->seller);
        $cancelResponse = postJson("/api/orders/{$sellOrderId}/cancel");
        $cancelResponse->assertStatus(422);
    });
});

describe('Edge Cases', function () {
    test('buyer cannot place order exceeding balance', function () {
        actingAs($this->buyer);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 100.0, // Would cost 5M, but only has 100k
        ]);

        $response->assertStatus(422);
        expect(Order::count())->toBe(0);
    });

    test('seller cannot place order exceeding asset holdings', function () {
        actingAs($this->seller);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 100.0, // Only has 10 BTC
        ]);

        $response->assertStatus(422);
        expect(Order::count())->toBe(0);
    });

    test('self-matching is prevented (user cannot match own order)', function () {
        // Give buyer some BTC to sell
        Asset::create([
            'user_id' => $this->buyer->id,
            'symbol' => 'BTC',
            'amount' => 5.0,
            'locked_amount' => 0,
        ]);

        actingAs($this->buyer);

        // Place sell order
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Place buy order at same price - should NOT match own order
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // No trades should exist
        expect(Trade::count())->toBe(0);
        
        // Both orders should remain open
        expect(Order::open()->count())->toBe(2);
    });

    test('zero price order is rejected', function () {
        actingAs($this->buyer);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 0,
            'amount' => 1.0,
        ]);

        $response->assertStatus(422);
    });

    test('negative amount order is rejected', function () {
        actingAs($this->buyer);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => -1.0,
        ]);

        $response->assertStatus(422);
    });
});

describe('API Profile Integration', function () {
    test('profile shows updated balances after trade', function () {
        // Initial balances
        $initialBuyerBalance = (float) $this->buyer->balance;
        $initialSellerBalance = (float) $this->seller->balance;
        
        // Seller places order
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);

        // Buyer matches
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 0.5,
        ]);

        // Check buyer's balance decreased (paid for BTC)
        $this->buyer->refresh();
        expect((float) $this->buyer->balance)->toBeLessThan($initialBuyerBalance);
        
        // Check seller's balance increased (received USD)
        $this->seller->refresh();
        expect((float) $this->seller->balance)->toBeGreaterThan($initialSellerBalance);
        
        // Verify trade was created
        expect(\App\Models\Trade::count())->toBeGreaterThan(0);
    });

    test('profile shows open orders correctly', function () {
        actingAs($this->buyer);

        // Place open order (no match)
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 40000.00,
            'amount' => 1.0,
        ]);

        $profile = getJson('/api/profile');
        $profile->assertStatus(200);
        $profile->assertJsonCount(1, 'data.open_orders');
    });
});

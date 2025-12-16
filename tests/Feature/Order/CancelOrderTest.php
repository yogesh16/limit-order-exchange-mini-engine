<?php

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create([
        'balance' => 10000.00,
    ]);

    $this->otherUser = User::factory()->create([
        'balance' => 5000.00,
    ]);
});

describe('Cancel Buy Order', function () {
    test('user can cancel their own open buy order', function () {
        actingAs($this->user);

        // Create a buy order
        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        // Deduct balance for the order
        $this->user->deductBalance(bcmul('95000.00', '0.1', 8));

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Order cancelled successfully',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);
    });

    test('cancelling buy order releases locked USD', function () {
        actingAs($this->user);

        $orderTotal = bcmul('95000.00', '0.1', 8); // 9500.00
        
        // Create order and deduct balance
        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        $this->user->refresh();
        $balanceBeforeCancel = $this->user->balance;
        $this->user->deductBalance($orderTotal);
        $this->user->refresh();

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);

        $this->user->refresh();
        $expectedBalance = bcadd((string) $balanceBeforeCancel, '0', 8);

        // Balance should be restored
        expect((string) $this->user->balance)->toBe($expectedBalance);
    });
});

describe('Cancel Sell Order', function () {
    beforeEach(function () {
        // Give user some BTC to sell
        Asset::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'amount' => 0.5, // 0.5 available (0.5 locked)
            'locked_amount' => 0.5,
        ]);
    });

    test('user can cancel their own open sell order', function () {
        actingAs($this->user);

        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96000.00,
            'amount' => 0.5,
            'status' => OrderStatus::OPEN,
        ]);

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);
    });

    test('cancelling sell order releases locked assets', function () {
        actingAs($this->user);

        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96000.00,
            'amount' => 0.5,
            'status' => OrderStatus::OPEN,
        ]);

        $asset = $this->user->assets()->where('symbol', 'BTC')->first();
        expect((string) $asset->amount)->toBe('0.50000000');
        expect((string) $asset->locked_amount)->toBe('0.50000000');

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);

        $asset->refresh();
        expect((string) $asset->amount)->toBe('1.00000000'); // 0.5 + 0.5 released
        expect((string) $asset->locked_amount)->toBe('0.00000000');
    });
});

describe('Cancel Order Restrictions', function () {
    test('user cannot cancel another users order', function () {
        actingAs($this->user);

        $otherOrder = Order::create([
            'user_id' => $this->otherUser->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        $response = postJson("/api/orders/{$otherOrder->id}/cancel");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized to cancel this order',
        ]);

        // Order should still be open
        $this->assertDatabaseHas('orders', [
            'id' => $otherOrder->id,
            'status' => OrderStatus::OPEN->value,
        ]);
    });

    test('user cannot cancel already filled order', function () {
        actingAs($this->user);

        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::FILLED,
            'filled_at' => now(),
        ]);

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Cannot cancel a filled order',
        ]);
    });

    test('user cannot cancel already cancelled order', function () {
        actingAs($this->user);

        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::CANCELLED,
        ]);

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Order is already cancelled',
        ]);
    });

    test('unauthenticated user cannot cancel order', function () {
        $order = Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        $response = postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(401);
    });
});

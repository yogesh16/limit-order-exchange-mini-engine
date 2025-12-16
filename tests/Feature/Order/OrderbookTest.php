<?php

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->user3 = User::factory()->create();
});

describe('Orderbook - Buy Orders', function () {
    test('can fetch open buy orders for symbol', function () {
        // Create buy orders for BTC
        Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 94500.00,
            'amount' => 0.25,
            'status' => OrderStatus::OPEN,
        ]);

        // Create an ETH order (should not appear)
        Order::create([
            'user_id' => $this->user3->id,
            'symbol' => 'ETH',
            'side' => OrderSide::BUY,
            'price' => 3500.00,
            'amount' => 1.0,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=buy');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'symbol', 'side', 'price', 'amount', 'status'],
            ],
        ]);
    });

    test('buy orders are sorted by price descending', function () {
        // Create unordered buy orders
        Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 94000.00, // Lowest
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00, // Highest - should be first
            'amount' => 0.2,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user3->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 94500.00, // Middle
            'amount' => 0.15,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=buy');

        $response->assertStatus(200);
        
        $orders = $response->json('data');
        expect($orders[0]['price'])->toBe('95000.00000000');
        expect($orders[1]['price'])->toBe('94500.00000000');
        expect($orders[2]['price'])->toBe('94000.00000000');
    });
});

describe('Orderbook - Sell Orders', function () {
    test('can fetch open sell orders for symbol', function () {
        // Create sell orders for BTC
        Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96000.00,
            'amount' => 0.15,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96500.00,
            'amount' => 0.2,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=sell');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });

    test('sell orders are sorted by price ascending', function () {
        // Create unordered sell orders
        Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 97000.00, // Highest
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96000.00, // Lowest - should be first
            'amount' => 0.2,
            'status' => OrderStatus::OPEN,
        ]);

        Order::create([
            'user_id' => $this->user3->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96500.00, // Middle
            'amount' => 0.15,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=sell');

        $response->assertStatus(200);
        
        $orders = $response->json('data');
        expect($orders[0]['price'])->toBe('96000.00000000');
        expect($orders[1]['price'])->toBe('96500.00000000');
        expect($orders[2]['price'])->toBe('97000.00000000');
    });
});

describe('Orderbook - Filtering', function () {
    test('only open orders are shown in orderbook', function () {
        // Create open order
        Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
        ]);

        // Create filled order (should not appear)
        Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 94000.00,
            'amount' => 0.2,
            'status' => OrderStatus::FILLED,
            'filled_at' => now(),
        ]);

        // Create cancelled order (should not appear)
        Order::create([
            'user_id' => $this->user3->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 93000.00,
            'amount' => 0.3,
            'status' => OrderStatus::CANCELLED,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=buy');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    });

    test('can fetch all orders without filters', function () {
        // Create various orders
        Order::factory()->count(3)->create([
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'status' => OrderStatus::OPEN,
        ]);

        Order::factory()->count(2)->create([
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    });

    test('unauthenticated user can view orderbook', function () {
        Order::factory()->count(2)->create([
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'status' => OrderStatus::OPEN,
        ]);

        // Orderbook should be public
        $response = getJson('/api/orders?symbol=BTC');

        $response->assertStatus(200);
    });
});

describe('Orderbook - FIFO Matching Preparation', function () {
    test('orders with same price are sorted by created_at for FIFO', function () {
        // Create orders with same price but different times
        $firstOrder = Order::create([
            'user_id' => $this->user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN,
            'created_at' => now()->subMinutes(10),
        ]);

        $secondOrder = Order::create([
            'user_id' => $this->user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.2,
            'status' => OrderStatus::OPEN,
            'created_at' => now()->subMinutes(5),
        ]);

        $thirdOrder = Order::create([
            'user_id' => $this->user3->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.3,
            'status' => OrderStatus::OPEN,
            'created_at' => now(),
        ]);

        actingAs($this->user1);
        $response = getJson('/api/orders?symbol=BTC&side=buy');

        $response->assertStatus(200);
        
        $orders = $response->json('data');
        // First created should come first (FIFO)
        expect($orders[0]['id'])->toBe($firstOrder->id);
        expect($orders[1]['id'])->toBe($secondOrder->id);
        expect($orders[2]['id'])->toBe($thirdOrder->id);
    });
});

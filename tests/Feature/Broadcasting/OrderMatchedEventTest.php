<?php

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\Event;

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

describe('Event Firing', function () {
    test('OrderMatched event fires when trade is executed', function () {
        Event::fake([OrderMatched::class]);

        // Seller places order
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Buyer places matching order
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Assert event was dispatched
        Event::assertDispatched(OrderMatched::class);
    });

    test('OrderMatched event is dispatched for each trade', function () {
        Event::fake([OrderMatched::class]);

        // Create second seller
        $seller2 = User::factory()->create(['balance' => 0]);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => 10.0,
            'locked_amount' => 0,
        ]);

        // First seller places order
        actingAs($this->seller);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ])->assertStatus(201);

        // Second seller places order
        actingAs($seller2);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000.00,
            'amount' => 0.5,
        ])->assertStatus(201);

        // Buyer matches both
        actingAs($this->buyer);
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        // Assert event was dispatched twice (one for each trade)
        Event::assertDispatchedTimes(OrderMatched::class, 2);
    });

    test('no event fires when orders do not match', function () {
        Event::fake([OrderMatched::class]);

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

        // Assert event was NOT dispatched
        Event::assertNotDispatched(OrderMatched::class);
    });
});

describe('Broadcast Channels', function () {
    test('event broadcasts to buyer private channel', function () {
        Event::fake([OrderMatched::class]);

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

        Event::assertDispatched(OrderMatched::class, function ($event) {
            $channels = $event->broadcastOn();
            $channelNames = array_map(fn($ch) => $ch->name, $channels);
            
            return in_array("private-user.{$this->buyer->id}", $channelNames);
        });
    });

    test('event broadcasts to seller private channel', function () {
        Event::fake([OrderMatched::class]);

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

        Event::assertDispatched(OrderMatched::class, function ($event) {
            $channels = $event->broadcastOn();
            $channelNames = array_map(fn($ch) => $ch->name, $channels);
            
            return in_array("private-user.{$this->seller->id}", $channelNames);
        });
    });
});

describe('Event Payload', function () {
    test('event contains correct trade data', function () {
        Event::fake([OrderMatched::class]);

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
        postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000.00,
            'amount' => 1.0,
        ])->assertStatus(201);

        Event::assertDispatched(OrderMatched::class, function (OrderMatched $event) {
            $payload = $event->broadcastWith();
            
            return isset($payload['trade']) &&
                   isset($payload['trade']['symbol']) &&
                   isset($payload['trade']['price']) &&
                   isset($payload['trade']['amount']) &&
                   $payload['trade']['symbol'] === 'BTC';
        });
    });

    test('event contains buyer and seller IDs', function () {
        Event::fake([OrderMatched::class]);

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

        Event::assertDispatched(OrderMatched::class, function (OrderMatched $event) {
            $payload = $event->broadcastWith();
            
            return isset($payload['buyer_id']) &&
                   isset($payload['seller_id']) &&
                   $payload['buyer_id'] === $this->buyer->id &&
                   $payload['seller_id'] === $this->seller->id;
        });
    });
});

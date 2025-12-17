<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->user = User::factory()->create([
        'balance' => 10000.00,
    ]);

    // Create some assets for the user
    Asset::create([
        'user_id' => $this->user->id,
        'symbol' => 'BTC',
        'amount' => 0.5,
        'locked_amount' => 0.1,
    ]);

    Asset::create([
        'user_id' => $this->user->id,
        'symbol' => 'ETH',
        'amount' => 5.0,
        'locked_amount' => 0,
    ]);
});

describe('Profile Endpoint', function () {
    test('authenticated user can view their profile', function () {
        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'balance',
                'assets',
            ],
        ]);
    });

    test('unauthenticated user cannot view profile', function () {
        $response = getJson('/api/profile');

        $response->assertStatus(401);
    });

    test('profile includes user balance', function () {
        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.balance', '10000.00000000');
    });

    test('profile includes all user assets', function () {
        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.assets');
    });

    test('assets include symbol, amount, and locked_amount', function () {
        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);

        $assets = $response->json('data.assets');
        $btcAsset = collect($assets)->firstWhere('symbol', 'BTC');

        expect($btcAsset)->not->toBeNull();
        expect($btcAsset['symbol'])->toBe('BTC');
        expect($btcAsset['amount'])->toBe('0.50000000');
        expect($btcAsset['locked_amount'])->toBe('0.10000000');
    });

    test('assets include total amount (available + locked)', function () {
        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);

        $assets = $response->json('data.assets');
        $btcAsset = collect($assets)->firstWhere('symbol', 'BTC');

        expect($btcAsset['total'])->toBe('0.60000000');
    });
});

describe('Profile with Orders', function () {
    test('profile includes open orders', function () {
        // Create an open order
        Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 50000.00,
            'amount' => 0.1,
            'filled_amount' => 0,
            'status' => OrderStatus::OPEN,
        ]);

        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'open_orders',
            ],
        ]);
        $response->assertJsonCount(1, 'data.open_orders');
    });

    test('profile open_orders only includes open status orders', function () {
        // Create open order
        Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 50000.00,
            'amount' => 0.1,
            'filled_amount' => 0,
            'status' => OrderStatus::OPEN,
        ]);

        // Create filled order
        Order::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 51000.00,
            'amount' => 0.1,
            'filled_amount' => 0.1,
            'status' => OrderStatus::FILLED,
        ]);

        actingAs($this->user);

        $response = getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.open_orders');
    });
});

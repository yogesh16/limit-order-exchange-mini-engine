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
});

describe('Buy Orders', function () {
    test('user can place buy order with sufficient balance', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => 0.1,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'symbol',
                'side',
                'price',
                'amount',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY->value,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => OrderStatus::OPEN->value,
        ]);
    });

    test('user cannot place buy order with insufficient balance', function () {
        actingAs($this->user);

        // Try to buy 1 BTC at 95000 (needs 95000 USD, but user only has 10000)
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => 1.0,
        ]);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Insufficient balance',
        ]);
    });

    test('balance is deducted correctly on buy order', function () {
        actingAs($this->user);

        $initialBalance = $this->user->balance;
        $orderTotal = bcmul('95000.00', '0.1', 8); // 9500.00

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => 0.1,
        ]);

        $response->assertStatus(201);

        $this->user->refresh();
        $expectedBalance = bcsub((string) $initialBalance, $orderTotal, 8);

        expect((string) $this->user->balance)->toBe($expectedBalance);
    });
});

describe('Sell Orders', function () {
    beforeEach(function () {
        // Give user some BTC to sell
        Asset::create([
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'amount' => 1.0,
            'locked_amount' => 0,
        ]);
    });

    test('user can place sell order with sufficient assets', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 96000.00,
            'amount' => 0.5,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL->value,
            'price' => 96000.00,
            'amount' => 0.5,
            'status' => OrderStatus::OPEN->value,
        ]);
    });

    test('user cannot place sell order with insufficient assets', function () {
        actingAs($this->user);

        // Try to sell 2 BTC but only have 1
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 96000.00,
            'amount' => 2.0,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Insufficient assets',
        ]);
    });

    test('user cannot sell asset they do not own', function () {
        actingAs($this->user);

        // Try to sell ETH without owning any
        $response = postJson('/api/orders', [
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => 3500.00,
            'amount' => 1.0,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Insufficient assets',
        ]);
    });

    test('asset amount is locked correctly on sell order', function () {
        actingAs($this->user);

        $asset = $this->user->assets()->where('symbol', 'BTC')->first();
        $initialAmount = $asset->amount;
        $orderAmount = 0.5;

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 96000.00,
            'amount' => $orderAmount,
        ]);

        $response->assertStatus(201);

        $asset->refresh();

        $expectedAmount = bcsub((string) $initialAmount, (string) $orderAmount, 8);
        $expectedLocked = bcadd((string) $orderAmount, '0', 8);

        expect((string) $asset->amount)->toBe($expectedAmount);
        expect((string) $asset->locked_amount)->toBe($expectedLocked);
    });
});

describe('Authentication & Validation', function () {
    test('unauthenticated user cannot place order', function () {
        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => 0.1,
        ]);

        $response->assertStatus(401);
    });

    test('validation errors for missing fields', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
    });

    test('validation errors for invalid side', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'invalid',
            'price' => 95000.00,
            'amount' => 0.1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['side']);
    });

    test('validation errors for negative price', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => -100.00,
            'amount' => 0.1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
    });

    test('validation errors for negative amount', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 95000.00,
            'amount' => -0.1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    });

    test('validation errors for unsupported symbol', function () {
        actingAs($this->user);

        $response = postJson('/api/orders', [
            'symbol' => 'DOGE',
            'side' => 'buy',
            'price' => 1.00,
            'amount' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol']);
    });
});

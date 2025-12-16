<?php

namespace Database\Seeders;

use App\Enums\OrderSide;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test users with varied balances
        $user1 = User::factory()->create([
            'name' => 'Alice Trader',
            'email' => 'alice@example.com',
            'balance' => 50000.00,
        ]);

        $user2 = User::factory()->create([
            'name' => 'Bob Investor',
            'email' => 'bob@example.com',
            'balance' => 25000.00,
        ]);

        $user3 = User::factory()->create([
            'name' => 'Charlie Crypto',
            'email' => 'charlie@example.com',
            'balance' => 10000.00,
        ]);

        // Create assets for users
        Asset::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'amount' => 2.5,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user1->id,
            'symbol' => 'ETH',
            'amount' => 15.0,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'amount' => 1.0,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'ETH',
            'amount' => 8.0,
            'locked_amount' => 0,
        ]);

        // Create sample open orders for orderbook testing
        // BTC Buy Orders
        Order::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 95000.00,
            'amount' => 0.1,
            'status' => 1,
        ]);

        Order::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::BUY,
            'price' => 94500.00,
            'amount' => 0.25,
            'status' => 1,
        ]);

        // BTC Sell Orders
        Order::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96000.00,
            'amount' => 0.15,
            'status' => 1,
        ]);

        Order::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'price' => 96500.00,
            'amount' => 0.2,
            'status' => 1,
        ]);

        // ETH Buy Orders
        Order::create([
            'user_id' => $user3->id,
            'symbol' => 'ETH',
            'side' => OrderSide::BUY,
            'price' => 3500.00,
            'amount' => 1.0,
            'status' => 1,
        ]);

        // ETH Sell Orders
        Order::create([
            'user_id' => $user1->id,
            'symbol' => 'ETH',
            'side' => OrderSide::SELL,
            'price' => 3600.00,
            'amount' => 2.0,
            'status' => 1,
        ]);

        $this->command->info('✅ Database seeded with test users, assets, and orders!');
    }
}

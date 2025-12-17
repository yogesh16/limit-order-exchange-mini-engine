<?php

namespace Database\Seeders;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
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
        // Create SYSTEM user for exchange liquidity
        $systemUser = User::factory()->create([
            'name' => 'Exchange System',
            'email' => 'system@exchange.local',
            'balance' => 1000000.00, // $1M USD for buying
        ]);

        // Give system user large asset holdings
        Asset::create([
            'user_id' => $systemUser->id,
            'symbol' => 'BTC',
            'amount' => 1000.0, // 1000 BTC
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $systemUser->id,
            'symbol' => 'ETH',
            'amount' => 10000.0, // 10000 ETH
            'locked_amount' => 0,
        ]);

        // Create pre-seeded SELL orders from system (users can buy from these)
        // BTC Sell Orders at various price points
        $btcSellPrices = [100, 105, 110, 120, 150, 200];
        foreach ($btcSellPrices as $price) {
            Order::create([
                'user_id' => $systemUser->id,
                'symbol' => 'BTC',
                'side' => OrderSide::SELL,
                'price' => $price,
                'amount' => 100.0, // 100 BTC available at each price
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);

            // Lock the assets for sell orders
            $asset = Asset::where('user_id', $systemUser->id)->where('symbol', 'BTC')->first();
            $asset->lockAmount(100.0);
        }

        // ETH Sell Orders at various price points
        $ethSellPrices = [10, 15, 20, 25, 30, 50];
        foreach ($ethSellPrices as $price) {
            Order::create([
                'user_id' => $systemUser->id,
                'symbol' => 'ETH',
                'side' => OrderSide::SELL,
                'price' => $price,
                'amount' => 500.0, // 500 ETH available at each price
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);

            // Lock the assets for sell orders
            $asset = Asset::where('user_id', $systemUser->id)->where('symbol', 'ETH')->first();
            $asset->lockAmount(500.0);
        }

        // Create pre-seeded BUY orders from system (users can sell to these)
        // BTC Buy Orders
        $btcBuyPrices = [50, 60, 70, 80, 90, 95];
        foreach ($btcBuyPrices as $price) {
            Order::create([
                'user_id' => $systemUser->id,
                'symbol' => 'BTC',
                'side' => OrderSide::BUY,
                'price' => $price,
                'amount' => 50.0, // Willing to buy 50 BTC at each price
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);
        }

        // ETH Buy Orders
        $ethBuyPrices = [5, 6, 7, 8, 9];
        foreach ($ethBuyPrices as $price) {
            Order::create([
                'user_id' => $systemUser->id,
                'symbol' => 'ETH',
                'side' => OrderSide::BUY,
                'price' => $price,
                'amount' => 200.0, // Willing to buy 200 ETH at each price
                'status' => OrderStatus::OPEN,
                'filled_amount' => 0,
            ]);
        }

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

        // Create assets for test users
        Asset::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'amount' => 10.0,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user1->id,
            'symbol' => 'ETH',
            'amount' => 100.0,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'amount' => 5.0,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'ETH',
            'amount' => 50.0,
            'locked_amount' => 0,
        ]);

        $this->command->info('✅ Database seeded with:');
        $this->command->info('   - System user with 1000 BTC, 10000 ETH, and $1M USD');
        $this->command->info('   - Pre-seeded BTC sell orders at $100-$200');
        $this->command->info('   - Pre-seeded ETH sell orders at $10-$50');
        $this->command->info('   - Pre-seeded BTC buy orders at $50-$95');
        $this->command->info('   - Pre-seeded ETH buy orders at $5-$9');
        $this->command->info('   - Test users Alice and Bob with assets');
    }
}

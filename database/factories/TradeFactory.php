<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
class TradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buy_order_id' => Order::factory()->buy()->filled(),
            'sell_order_id' => Order::factory()->sell()->filled(),
            'symbol' => 'BTC',
            'price' => fake()->randomFloat(8, 1000, 100000),
            'amount' => fake()->randomFloat(8, 0.01, 1),
            'commission' => fake()->randomFloat(8, 1, 100),
        ];
    }
}

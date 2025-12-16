<?php

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC', 'ETH'];
        $sides = [OrderSide::BUY, OrderSide::SELL];

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'side' => fake()->randomElement($sides),
            'price' => fake()->randomFloat(8, 1000, 100000), // Price between 1k-100k USD
            'amount' => fake()->randomFloat(8, 0.01, 1), // Amount between 0.01-1 crypto
            'status' => OrderStatus::OPEN,
            'filled_at' => null,
        ];
    }

    /**
     * Indicate that the order is a buy order.
     */
    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => OrderSide::BUY,
        ]);
    }

    /**
     * Indicate that the order is a sell order.
     */
    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => OrderSide::SELL,
        ]);
    }

    /**
     * Indicate that the order is filled.
     */
    public function filled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::FILLED,
            'filled_at' => now(),
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    /**
     * Set specific symbol for the order.
     */
    public function forSymbol(string $symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => strtoupper($symbol),
        ]);
    }
}

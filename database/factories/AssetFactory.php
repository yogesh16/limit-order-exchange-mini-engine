<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC', 'ETH'];

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'amount' => fake()->randomFloat(8, 0.1, 10), // 0.1 to 10 crypto
            'locked_amount' => 0,
        ];
    }

    /**
     * Indicate that the asset has some locked amount.
     */
    public function withLockedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_amount' => fake()->randomFloat(8, 0.01, 1),
        ]);
    }
}

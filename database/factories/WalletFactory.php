<?php

namespace Database\Factories;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'starting_balance' => fake()->randomFloat(2, 0, 10000),
        ];
    }
}

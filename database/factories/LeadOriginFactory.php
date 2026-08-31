<?php

namespace Database\Factories;

use App\Models\LeadOrigin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadOrigin>
 */
class LeadOriginFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}

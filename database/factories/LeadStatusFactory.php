<?php

namespace Database\Factories;

use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sort_order' => fake()->unique()->numberBetween(1, 100000),
            'name' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 500, 50000),
            'estimated_months' => fake()->numberBetween(1, 12),
            'description' => fake()->sentence(),
            'next_step' => fake()->sentence(),
        ];
    }
}

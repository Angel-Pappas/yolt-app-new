<?php

namespace Database\Factories;

use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectStatus>
 */
class ProjectStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}

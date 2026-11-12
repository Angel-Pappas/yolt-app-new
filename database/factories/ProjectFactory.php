<?php

namespace Database\Factories;

use App\Models\Entity;
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
            'entity_id' => Entity::factory()->state(['type' => 'customer']),
            'name' => fake()->words(3, true),
            'agreed_total' => null,
            'notes' => null,
            'is_excluded' => false,
        ];
    }
}

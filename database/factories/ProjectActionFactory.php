<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAction>
 */
class ProjectActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'action_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'body' => fake()->sentence(),
            'author_name' => fake()->name(),
        ];
    }
}

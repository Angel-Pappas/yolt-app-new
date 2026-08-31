<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadAction>
 */
class LeadActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'action_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'body' => fake()->sentence(),
            'author_name' => fake()->name(),
        ];
    }
}

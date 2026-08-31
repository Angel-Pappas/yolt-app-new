<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sort_order' => fake()->unique()->numberBetween(1, 100000),
            'name' => fake()->company(),
            'website' => fake()->domainName(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->numerify('##########'),
            'description' => fake()->sentence(),
            'next_step' => fake()->sentence(),
        ];
    }
}

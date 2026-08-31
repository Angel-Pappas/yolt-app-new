<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadContact>
 */
class LeadContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
        ];
    }
}

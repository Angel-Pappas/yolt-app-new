<?php

namespace Database\Factories;

use App\Models\PublicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicHoliday>
 */
class PublicHolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->date(),
            'name' => fake()->randomElement(['New Year', 'Independence Day', 'Easter Monday', 'Labour Day']),
        ];
    }
}

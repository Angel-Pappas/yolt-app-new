<?php

namespace Database\Factories;

use App\Models\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VatRate>
 */
class VatRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('VAT ##%'),
            'rate' => fake()->randomElement([6, 13, 24]),
        ];
    }
}

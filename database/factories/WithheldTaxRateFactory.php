<?php

namespace Database\Factories;

use App\Models\WithheldTaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithheldTaxRate>
 */
class WithheldTaxRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('Withholding ##%'),
            'rate' => fake()->randomElement([1, 4, 8, 20]),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\IncomeTaxYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeTaxYear>
 */
class IncomeTaxYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2020, 2030),
            'revenue_before_tax' => 100000,
            'total_tax' => 22000,
            'first_installment_month' => '2026-06-01',
            'last_installment_month' => '2027-01-01',
            'monthly_installment_amount' => 2750,
        ];
    }
}

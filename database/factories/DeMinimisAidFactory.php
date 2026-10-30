<?php

namespace Database\Factories;

use App\Models\DeMinimisAid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeMinimisAid>
 */
class DeMinimisAidFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 500, 20000);

        return [
            'programme' => fake()->sentence(6),
            'decision_number' => fake()->unique()->bothify('ACC-########'),
            'decision_date' => fake()->date(),
            'approved_amount' => $amount,
            'paid_amount' => $amount,
            'last_payment_date' => fake()->date(),
            'beneficiary_name' => 'YOLT LABS Ι Κ Ε',
            'beneficiary_vat_number' => '801487298',
        ];
    }
}

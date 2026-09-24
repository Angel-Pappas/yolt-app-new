<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory()->supplier(),
            'type' => 'expense',
            'description' => fake()->sentence(3),
            'category_id' => null,
            'wallet_id' => Wallet::factory(),
            'is_payroll' => false,
            'interval_count' => 1,
            'interval_unit' => 'month',
            'day_of_month' => 1,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => null,
            'active' => true,
        ];
    }
}

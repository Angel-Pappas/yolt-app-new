<?php

namespace Database\Factories;

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurrenceEntry>
 */
class RecurrenceEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recurrence_id' => Recurrence::factory(),
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => null,
            'net' => 100,
            'position' => 0,
        ];
    }
}

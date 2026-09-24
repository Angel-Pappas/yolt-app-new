<?php

namespace Database\Factories;

use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurrenceEntryLine>
 */
class RecurrenceEntryLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recurrence_entry_id' => RecurrenceEntry::factory(),
            'amount' => 100,
            'vat_rate_id' => null,
            'withheld_rate_id' => null,
            'position' => 0,
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\RecurrenceEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One dated amount line of a recurrence — the amount(s) in force for its span.
 *
 * @property int $id
 * @property int $recurrence_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string $net
 * @property string|null $fmy_amount
 * @property string|null $efka_employee_amount
 * @property string|null $efka_employer_amount
 * @property int $position
 */
#[Fillable([
    'recurrence_id',
    'start_date',
    'end_date',
    'net',
    'fmy_amount',
    'efka_employee_amount',
    'efka_employer_amount',
    'position',
])]
class RecurrenceEntry extends Model
{
    /** @use HasFactory<RecurrenceEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'net' => 'decimal:2',
            'fmy_amount' => 'decimal:2',
            'efka_employee_amount' => 'decimal:2',
            'efka_employer_amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Recurrence, $this> */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }
}

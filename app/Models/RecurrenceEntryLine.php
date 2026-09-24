<?php

namespace App\Models;

use App\Support\AmountLines;
use Database\Factories\RecurrenceEntryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One amount line of a recurrence period — the recurring twin of a transaction's
 * amount line: the typed `amount` (net or total, per the period's `amount_mode`),
 * its VAT rate, and an optional withholding rate. Resolved into money by
 * {@see AmountLines} exactly like a hand-entered line.
 *
 * @property int $id
 * @property int $recurrence_entry_id
 * @property string $amount
 * @property int|null $vat_rate_id
 * @property int|null $withheld_rate_id
 * @property int $position
 */
#[Fillable([
    'recurrence_entry_id',
    'amount',
    'vat_rate_id',
    'withheld_rate_id',
    'position',
])]
class RecurrenceEntryLine extends Model
{
    /** @use HasFactory<RecurrenceEntryLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'vat_rate_id' => 'integer',
            'withheld_rate_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<RecurrenceEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(RecurrenceEntry::class, 'recurrence_entry_id');
    }
}

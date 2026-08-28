<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $transaction_id
 * @property string $net
 * @property int|null $withheld_rate_id
 * @property string $withheld_amount
 * @property int $position
 */
#[Fillable(['net', 'withheld_rate_id', 'withheld_amount', 'position'])]
class TransactionWithheldLine extends Model
{
    protected function casts(): array
    {
        return [
            'net' => 'decimal:2',
            'withheld_amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<WithheldTaxRate, $this> */
    public function withheldRate(): BelongsTo
    {
        return $this->belongsTo(WithheldTaxRate::class);
    }
}

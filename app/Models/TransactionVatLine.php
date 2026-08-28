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
 * @property int|null $vat_rate_id
 * @property string $vat_amount
 * @property int $position
 */
#[Fillable(['net', 'vat_rate_id', 'vat_amount', 'position'])]
class TransactionVatLine extends Model
{
    protected function casts(): array
    {
        return [
            'net' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }
}

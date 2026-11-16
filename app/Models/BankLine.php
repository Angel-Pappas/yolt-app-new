<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of an imported bank statement. It is done once linked to the transaction
 * it confirmed or became, or once dismissed (handled some other way); until then it
 * waits on the Bank import page with the app's suggestion.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $wallet_id
 * @property int|null $transaction_id
 * @property Carbon $date
 * @property int $position
 * @property string $text
 * @property string $amount
 * @property string $reference
 * @property string $fingerprint
 * @property bool $is_dismissed
 */
#[Fillable(['wallet_id', 'transaction_id', 'date', 'position', 'text', 'amount', 'reference', 'fingerprint', 'is_dismissed'])]
class BankLine extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'position' => 'integer',
            'amount' => 'decimal:2',
            'is_dismissed' => 'boolean',
        ];
    }

    /**
     * Lines still needing a decision: not dismissed and not linked to a live
     * transaction (deleting the transaction a line became puts the line back).
     *
     * @param  Builder<BankLine>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dismissed', false)->whereDoesntHave('transaction');
    }

    /** Whether the line still needs a decision (see {@see scopePending()}). */
    public function isPending(): bool
    {
        return ! $this->is_dismissed && $this->transaction === null;
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

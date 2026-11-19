<?php

namespace App\Models;

use App\Support\BankReconciler;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One line of an imported bank statement. It is done once paired with the app's
 * row(s) it is — usually one, sometimes several that add up to it (a repayment and
 * its cost booked as one line); until then the Bank import page shows it as missing
 * from the app (or next to the row(s) the app thinks it is).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $wallet_id
 * @property Carbon $date
 * @property int $position
 * @property string $text
 * @property string $amount
 * @property string $reference
 * @property string $fingerprint
 */
#[Fillable(['wallet_id', 'date', 'position', 'text', 'amount', 'reference', 'fingerprint'])]
class BankLine extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'position' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Lines not paired with any live row yet (deleting the row a line became puts
     * the line back).
     *
     * @param  Builder<BankLine>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereDoesntHave('transactions');
    }

    /** Whether the line is still unpaired (see {@see scopePending()}). */
    public function isPending(): bool
    {
        return $this->transactions->isEmpty();
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * The app's rows this line is, in the order they were paired.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('id');
    }

    /** What the line's rows move together, as a positive amount. */
    public function pairedCash(): float
    {
        return round($this->transactions->sum(fn (Transaction $t): float => BankReconciler::cash($t)), 2);
    }
}

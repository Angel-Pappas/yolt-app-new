<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One uploaded bank statement of a wallet. While **open** it is a draft: every
 * decision about its lines is a saved step ({@see BankStatementStep}), undoable, and
 * nothing in the books changes. **Concluding** it applies the decisions in one go and
 * moves the wallet's concluded date to its last day.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $wallet_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string|null $closing_balance
 * @property string $status
 * @property Carbon|null $concluded_at
 * @property int|null $concluded_by
 * @property Carbon $created_at
 */
#[Fillable(['wallet_id', 'from_date', 'to_date', 'closing_balance'])]
class BankStatement extends Model
{
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'closing_balance' => 'decimal:2',
            'concluded_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function concluder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluded_by');
    }

    /** @return HasMany<BankLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BankLine::class);
    }

    /** @return HasMany<BankStatementStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(BankStatementStep::class)->orderBy('id');
    }
}

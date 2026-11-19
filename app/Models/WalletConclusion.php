<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time a wallet's books were declared complete up to a date — by concluding a
 * bank statement, or by hand for a wallet without statements. The history behind
 * `wallets.concluded_until`.
 *
 * @property int $id
 * @property int $wallet_id
 * @property int|null $bank_statement_id
 * @property int|null $user_id
 * @property Carbon $until
 * @property Carbon $created_at
 */
#[Fillable(['wallet_id', 'bank_statement_id', 'user_id', 'until'])]
class WalletConclusion extends Model
{
    protected function casts(): array
    {
        return ['until' => 'date'];
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
}

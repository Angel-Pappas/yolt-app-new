<?php

namespace App\Models;

use App\Support\Plan;
use Illuminate\Database\Eloquent\Builder;

/**
 * A row of the plan that is kept, not computed: a planned **one-off** (expense,
 * `is_one_off`) or a customer **payment line** (income). It lives in the
 * `transactions` table — so it has the same amounts, VAT and withholding lines and
 * form as a real transaction — flagged `is_planned`, which keeps it out of the books
 * ({@see Transaction}'s global scope). Real rows that pay it carry its plan key
 * `planned:{id}` in `managed_key`; its open amount is what they haven't covered yet
 * ({@see Plan}).
 */
class PlannedTransaction extends Transaction
{
    protected $table = 'transactions';

    protected static function booted(): void
    {
        static::addGlobalScope('planned', fn (Builder $q) => $q->where('transactions.is_planned', true));
        static::creating(function (PlannedTransaction $t): void {
            $t->is_planned = true;
        });
    }

    public function getForeignKey(): string
    {
        return 'transaction_id';
    }

    /** This row's key in the plan. */
    public function planKey(): string
    {
        return "planned:{$this->id}";
    }
}

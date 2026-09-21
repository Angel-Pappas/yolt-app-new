<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Current wallet balances, derived live (never stored, so they can't drift):
 * each wallet's `starting_balance` plus every active transaction's effect.
 * Income/expense move the cash total (net + VAT − withheld − FMY − employee EFKA —
 * the last two are the payroll amounts withheld from the employee, so a payroll
 * expense moves the wallet by its "To Pay"; employer EFKA is a liability that never
 * touches the wallet); a transfer moves net out of `wallet_id` into `to_wallet_id`.
 */
class WalletBalances
{
    /**
     * The complete chronological history of a single wallet, each transaction
     * annotated with a `balance` attribute = the running balance after it (seeded
     * from the wallet's starting balance). Because a running balance depends on
     * every prior row, this always walks the wallet's full history; display
     * filters are applied to the result afterwards, not to this walk.
     *
     * @return Collection<int, Transaction>
     */
    public static function runningFor(int $walletId, float $startingBalance): Collection
    {
        $running = $startingBalance;

        return Transaction::query()
            ->withListData()
            ->where(fn ($q) => $q->where('wallet_id', $walletId)->orWhere('to_wallet_id', $walletId))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (Transaction $t) use (&$running, $walletId): void {
                $total = self::cashTotal($t);

                if ($t->type === 'income' && $t->wallet_id === $walletId) {
                    $running += $total;
                } elseif ($t->type === 'expense' && $t->wallet_id === $walletId) {
                    $running -= $total;
                } elseif ($t->type === 'transfer') {
                    if ($t->wallet_id === $walletId) {
                        $running -= (float) $t->net;
                    }
                    if ($t->to_wallet_id === $walletId) {
                        $running += (float) $t->net;
                    }
                }

                $t->setAttribute('balance', round($running, 2));
            });
    }

    /**
     * The cash a transaction actually moves: net + VAT − withheld − FMY − employee
     * EFKA. The two payroll amounts default to 0 for every non-payroll row (they are
     * null there), so this reduces to the plain net + VAT − withheld everywhere else.
     * Employer EFKA is deliberately excluded — it is a liability, not cash out.
     */
    public static function cashTotal(Transaction $t): float
    {
        return (float) $t->net
            + (float) $t->vat_amount
            - (float) $t->withheld_amount
            - (float) $t->fmy_amount
            - (float) $t->efka_employee_amount;
    }

    /**
     * The current balance of each wallet — as of today. Only transactions dated up
     * to today count; future-dated rows (auto-generated recurrences and taxes, or a
     * manually post-dated entry) are projections, shown running forward in the
     * balance view, not folded into the headline current balance.
     *
     * @return array<int, float> wallet id => current balance
     */
    public static function all(): array
    {
        $balances = [];
        foreach (Wallet::query()->get(['id', 'starting_balance']) as $wallet) {
            $balances[$wallet->id] = (float) $wallet->starting_balance;
        }

        Transaction::query()
            ->where('date', '<=', Carbon::today()->toDateString())
            ->get(['type', 'net', 'vat_amount', 'withheld_amount', 'fmy_amount', 'efka_employee_amount', 'wallet_id', 'to_wallet_id'])
            ->each(function (Transaction $t) use (&$balances): void {
                $total = self::cashTotal($t);

                if ($t->type === 'income') {
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) + $total;
                } elseif ($t->type === 'expense') {
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) - $total;
                } elseif ($t->type === 'transfer') {
                    $net = (float) $t->net;
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) - $net;
                    if ($t->to_wallet_id !== null) {
                        $balances[$t->to_wallet_id] = ($balances[$t->to_wallet_id] ?? 0.0) + $net;
                    }
                }
            });

        return array_map(fn (float $b): float => round($b, 2), $balances);
    }
}

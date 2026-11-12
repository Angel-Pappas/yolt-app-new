<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The cash-flow projection: money in and out per month, and the balance it leaves —
 * the past from what happened, the future from what is planned (recurrences, project
 * installments, one-offs, and the taxes they cause). Derived live from the
 * transactions that count, by payment `date`; never stored.
 *
 * Money out is split the way the company looks at it: **payroll** (an employee, or
 * the Payroll category), **contractors**, **taxes** (the generated tax payments and
 * anything paid to the State) and **other** expenses. Amounts are cash (what the
 * wallet moves: net + VAT − withheld − FMY − employee EFKA).
 *
 * A month's starting balance is the wallets' starting balances plus everything
 * before it. Options: limit to some wallets (transfers to or from the others then
 * show as their own column), and whether rows dated before today that are still
 * unreconciled count (they are still expected) or are left out (only what really
 * happened in the past).
 */
class Projection
{
    public const KINDS = ['income', 'payroll', 'contractors', 'taxes', 'other'];

    /**
     * @param  list<int>|null  $walletIds  null = every wallet
     * @return list<array{month: string, starting: float, income: float, payroll: float, contractors: float, taxes: float, other: float, expenses: float, transfers: float, net: float, ending: float}>
     */
    public static function monthly(string $fromMonth, string $toMonth, ?array $walletIds = null, bool $countPending = true): array
    {
        $wallets = Wallet::query()
            ->when($walletIds !== null, fn ($q) => $q->whereIn('id', $walletIds))
            ->get(['id', 'starting_balance']);
        $ids = $wallets->pluck('id')->flip();
        $balance = (float) $wallets->sum(fn (Wallet $w) => (float) $w->starting_balance);

        $today = Carbon::today()->toDateString();
        $first = $fromMonth.'-01';
        $last = Carbon::createFromFormat('Y-m-d', $toMonth.'-01')->endOfMonth()->toDateString();

        /** @var array<string, array<string, float>> $months */
        $months = [];
        for ($m = Carbon::createFromFormat('Y-m-d', $first); $m->toDateString() <= $last; $m->addMonth()) {
            $months[$m->format('Y-m')] = array_fill_keys([...self::KINDS, 'transfers'], 0.0);
        }

        Transaction::query()
            ->counted()
            ->whereDate('date', '<=', $last)
            ->with(['entity:id,type', 'category:id,name'])
            ->orderBy('date')
            ->get()
            ->each(function (Transaction $t) use (&$months, &$balance, $ids, $today, $first, $countPending): void {
                $date = $t->date->toDateString();
                if (! $countPending && ! $t->is_reconciled && $date < $today) {
                    return;
                }

                [$kind, $amount] = self::effect($t, $ids);
                if ($amount === 0.0) {
                    return;
                }

                if ($date < $first) {
                    $balance += $kind === 'income' || $kind === 'transfers' ? $amount : -$amount;

                    return;
                }
                $months[$t->date->format('Y-m')][$kind] += $amount;
            });

        $rows = [];
        foreach ($months as $month => $sums) {
            $expenses = $sums['payroll'] + $sums['contractors'] + $sums['taxes'] + $sums['other'];
            $net = $sums['income'] - $expenses + $sums['transfers'];
            $rows[] = [
                'month' => $month,
                'starting' => round($balance, 2),
                'income' => round($sums['income'], 2),
                'payroll' => round($sums['payroll'], 2),
                'contractors' => round($sums['contractors'], 2),
                'taxes' => round($sums['taxes'], 2),
                'other' => round($sums['other'], 2),
                'expenses' => round($expenses, 2),
                'transfers' => round($sums['transfers'], 2),
                'net' => round($net, 2),
                'ending' => round($balance + $net, 2),
            ];
            $balance += $net;
        }

        return $rows;
    }

    /**
     * What a transaction does to the chosen wallets: its kind and a positive amount
     * (a transfer's is signed: + into the chosen wallets, − out of them, 0 within).
     *
     * @param  Collection<int, int>  $ids  the chosen wallet ids (as keys)
     * @return array{0: string, 1: float}
     */
    private static function effect(Transaction $t, Collection $ids): array
    {
        if ($t->type === 'transfer') {
            $out = $ids->has($t->wallet_id);
            $in = $t->to_wallet_id !== null && $ids->has($t->to_wallet_id);

            return ['transfers', $in === $out ? 0.0 : ($in ? (float) $t->net : -(float) $t->net)];
        }

        if (! $ids->has($t->wallet_id)) {
            return ['other', 0.0];
        }

        $amount = WalletBalances::cashTotal($t);
        if ($t->type === 'income') {
            return ['income', $amount];
        }

        return [self::expenseKind($t), $amount];
    }

    /** Which money-out column an expense belongs to. */
    public static function expenseKind(Transaction $t): string
    {
        $entityType = $t->entity?->type;

        return match (true) {
            $t->source === 'tax', $entityType === 'state' => 'taxes',
            $entityType === 'employee', $t->category?->name === AmountLines::PAYROLL_CATEGORY => 'payroll',
            $entityType === 'contractor' => 'contractors',
            default => 'other',
        };
    }
}

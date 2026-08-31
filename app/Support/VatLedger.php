<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The monthly Greek VAT ledger — derived live from transactions, never stored.
 *
 * VAT is attributed to the month a transaction was **invoiced** (`invoice_date`),
 * per Greek VAT law. Each period's raw net is output VAT (from income) minus input
 * VAT (from expenses); what is actually *payable* threads two pieces of state
 * forward across every month, so the ledger must be walked chronologically over
 * the complete history (later months depend on earlier ones):
 *
 *  - **Credit rollover** — a negative net (πιστωτικό) is a credit that carries
 *    forward indefinitely to offset a later month's debit.
 *  - **Installments** — a positive net over €100 is split into two equal
 *    interest-free installments (half due with that month's own filing, half the
 *    following month); €100 or under is paid in full immediately. The installment
 *    option is always taken.
 *
 * Every calendar month between the earliest VAT-bearing transaction and the later
 * of (latest transaction, today) is emitted, including zero-activity gap months —
 * a carried credit or a deferred installment still has to pass through a quiet
 * month to reach the next active one.
 */
class VatLedger
{
    /**
     * @return list<array{
     *     month: string,
     *     income_vat: float,
     *     expense_vat: float,
     *     net: float,
     *     rollover_in: float,
     *     payable_this_month: float,
     *     payable_next_month: float,
     * }>
     */
    public static function monthly(): array
    {
        /** @var array<string, array{income: float, expense: float}> $byMonth */
        $byMonth = [];

        Transaction::query()
            ->whereIn('type', ['income', 'expense'])
            ->get(['type', 'invoice_date', 'vat_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->invoice_date, 0, 7);
                $byMonth[$key] ??= ['income' => 0.0, 'expense' => 0.0];
                if ($t->type === 'income') {
                    $byMonth[$key]['income'] += (float) $t->vat_amount;
                } else {
                    $byMonth[$key]['expense'] += (float) $t->vat_amount;
                }
            });

        if ($byMonth === []) {
            return [];
        }

        $keys = array_keys($byMonth);
        sort($keys);
        // Append "-01": Carbon::createFromFormat('Y-m', …) would otherwise inherit
        // today's day-of-month and overflow a short month (Feb on the 31st → Mar).
        $start = Carbon::createFromFormat('Y-m-d', $keys[0].'-01')->startOfMonth();
        $end = Carbon::createFromFormat('Y-m-d', end($keys).'-01')->startOfMonth();
        $now = Carbon::now()->startOfMonth();
        if ($now->greaterThan($end)) {
            $end = $now;
        }

        $rows = [];
        $creditCarry = 0.0;   // credit available to offset a debit (>= 0)
        $deferredIn = 0.0;    // installment deferred from the previous month

        for ($m = $start->copy(); $m->lessThanOrEqualTo($end); $m->addMonth()) {
            $key = $m->format('Y-m');
            $incomeVat = round($byMonth[$key]['income'] ?? 0.0, 2);
            $expenseVat = round($byMonth[$key]['expense'] ?? 0.0, 2);
            $net = round($incomeVat - $expenseVat, 2);

            $rolloverIn = $creditCarry;
            $adjusted = round($net - $creditCarry, 2);

            if ($adjusted < 0) {
                $creditCarry = round(-$adjusted, 2);
                $ownDueNow = 0.0;
                $deferToNext = 0.0;
            } else {
                $creditCarry = 0.0;
                if ($adjusted > 100) {
                    $ownDueNow = round($adjusted / 2, 2);
                    $deferToNext = round($adjusted - $ownDueNow, 2);
                } else {
                    $ownDueNow = $adjusted;
                    $deferToNext = 0.0;
                }
            }

            $rows[] = [
                'month' => $key,
                'income_vat' => $incomeVat,
                'expense_vat' => $expenseVat,
                'net' => $net,
                'rollover_in' => $rolloverIn,
                'payable_this_month' => round($ownDueNow + $deferredIn, 2),
                'payable_next_month' => $deferToNext,
            ];

            $deferredIn = $deferToNext;
        }

        return $rows;
    }
}

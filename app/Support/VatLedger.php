<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The monthly Greek VAT ledger — derived live from transactions, never stored.
 *
 * VAT is attributed to the month a transaction was **invoiced** (`invoice_date`),
 * per Greek VAT law. Each period's raw net is output VAT (income) minus input VAT
 * (expenses). A period's bucket is **paid on the last working day of the following
 * month**. What is actually *payable* threads one piece of state forward across
 * every month, so the ledger is walked chronologically over the complete history:
 *
 *  - **Credit rollover** — a negative net (πιστωτικό) is a credit that carries
 *    forward indefinitely to offset a later month's debit.
 *
 * A positive net (after any credit) is paid in **one** payment — no installment
 * split (that option is not modelled for now).
 *
 * Every calendar month between the earliest VAT-bearing transaction and the later
 * of (latest transaction, today) is emitted, including zero-activity gap months —
 * a carried credit still has to pass through a quiet month to reach the next one.
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
     *     payable: float,
     *     due_date: string,
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

        $holidays = WorkingDays::holidaySet();
        $rows = [];
        $creditCarry = 0.0;   // credit available to offset a debit (>= 0)

        for ($m = $start->copy(); $m->lessThanOrEqualTo($end); $m->addMonth()) {
            $key = $m->format('Y-m');
            $incomeVat = round($byMonth[$key]['income'] ?? 0.0, 2);
            $expenseVat = round($byMonth[$key]['expense'] ?? 0.0, 2);
            $net = round($incomeVat - $expenseVat, 2);

            $rolloverIn = $creditCarry;
            $adjusted = round($net - $creditCarry, 2);

            if ($adjusted < 0) {
                $creditCarry = round(-$adjusted, 2);
                $payable = 0.0;
            } else {
                $creditCarry = 0.0;
                $payable = $adjusted;
            }

            $due = $m->copy()->addMonth();
            $rows[] = [
                'month' => $key,
                'income_vat' => $incomeVat,
                'expense_vat' => $expenseVat,
                'net' => $net,
                'rollover_in' => $rolloverIn,
                'payable' => $payable,
                'due_date' => WorkingDays::lastWorkingDay($due->year, $due->month, $holidays)->format('Y-m-d'),
            ];
        }

        return $rows;
    }

    /**
     * The payment obligations this ledger produces — one per month with a positive
     * amount owed.
     *
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        $out = [];
        foreach (self::monthly() as $row) {
            if ($row['payable'] > 0) {
                $out[] = new TaxObligation('vat', $row['month'], $row['payable'], $row['due_date']);
            }
        }

        return $out;
    }
}

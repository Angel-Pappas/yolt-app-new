<?php

namespace App\Support;

use App\Models\Transaction;

/**
 * The monthly EFKA (social security) ledger — derived live from payroll
 * transactions. Both the **employee** and **employer** EFKA amounts are collected in
 * a month and paid to the state **as one payment** on the last working day of the
 * following month, so the bucket is their sum. Attributed by `invoice_date`. A simple
 * monthly tax — see {@see MonthlyLedger}.
 */
class EfkaLedger
{
    /**
     * @return list<array{month: string, amount: float, due_date: string}>
     */
    public static function monthly(): array
    {
        return MonthlyLedger::build('efka', self::byMonth())['rows'];
    }

    /**
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        return MonthlyLedger::build('efka', self::byMonth())['obligations'];
    }

    /**
     * @return array<string, float> invoice-month 'YYYY-MM' => total EFKA (employee + employer)
     */
    private static function byMonth(): array
    {
        $byMonth = [];

        Transaction::query()
            ->where(function ($q): void {
                $q->where('efka_employee_amount', '>', 0)
                    ->orWhere('efka_employer_amount', '>', 0);
            })
            ->get(['invoice_date', 'efka_employee_amount', 'efka_employer_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->invoice_date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0.0)
                    + (float) $t->efka_employee_amount
                    + (float) $t->efka_employer_amount;
            });

        return $byMonth;
    }
}

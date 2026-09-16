<?php

namespace App\Support;

use App\Models\Transaction;

/**
 * The monthly FMY (payroll income-tax withholding) ledger — derived live from the
 * `fmy_amount` on payroll transactions, attributed by `invoice_date`, remitted on
 * the last working day of the following month. A simple monthly tax — see
 * {@see MonthlyLedger}.
 */
class FmyLedger
{
    /**
     * @return list<array{month: string, amount: float, due_date: string}>
     */
    public static function monthly(): array
    {
        return MonthlyLedger::build('fmy', self::byMonth())['rows'];
    }

    /**
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        return MonthlyLedger::build('fmy', self::byMonth())['obligations'];
    }

    /**
     * @return array<string, float> invoice-month 'YYYY-MM' => total FMY
     */
    private static function byMonth(): array
    {
        $byMonth = [];

        Transaction::query()
            ->where('fmy_amount', '>', 0)
            ->get(['invoice_date', 'fmy_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->invoice_date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0.0) + (float) $t->fmy_amount;
            });

        return $byMonth;
    }
}

<?php

namespace App\Support;

use App\Models\Transaction;

/**
 * The monthly withholding-tax remittance ledger — derived live, never stored.
 *
 * Withholding kept back on expense transactions in a month is remitted to the state
 * on the **last working day of the following month**. Like every tax it is
 * attributed by **`invoice_date`**, not payment date. Income-side withholding, if
 * ever recorded, is the client's liability and is not summed here. A simple monthly
 * tax — see {@see MonthlyLedger}.
 */
class WithheldLedger
{
    /**
     * @return list<array{month: string, amount: float, due_date: string}>
     */
    public static function monthly(): array
    {
        return MonthlyLedger::build('withheld', self::byMonth())['rows'];
    }

    /**
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        return MonthlyLedger::build('withheld', self::byMonth())['obligations'];
    }

    /**
     * @return array<string, float> invoice-month 'YYYY-MM' => total withheld
     */
    private static function byMonth(): array
    {
        $byMonth = [];

        Transaction::query()
            ->where('type', 'expense')
            ->where('withheld_amount', '>', 0)
            ->get(['invoice_date', 'withheld_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->invoice_date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0.0) + (float) $t->withheld_amount;
            });

        return $byMonth;
    }
}

<?php

namespace App\Support;

use App\Models\Transaction;

/**
 * Bring everything that is calculated back in line after a rule changes (a dated
 * VAT/withholding rate, the tax rules, an employee's details): the future is
 * recalculated, the past is left as it happened.
 *
 *  - Hand-entered transactions that are **not reconciled** get their VAT and
 *    withholding re-derived from their lines at the rate in force on their invoice
 *    date. Reconciled rows are frozen.
 *  - Every recurrence is re-synced (its unreconciled rows follow the new rules).
 *  - The tax payments are rebuilt from the result.
 */
class Recalculate
{
    public static function everything(): void
    {
        Transaction::query()
            ->whereNull('source')
            ->where('is_reconciled', false)
            ->whereIn('type', ['income', 'expense'])
            ->whereHas('vatLines')
            ->with(['vatLines', 'withheldLines'])
            ->get()
            ->each(fn (Transaction $t) => self::reapplyRates($t));

        RecurrenceGenerator::syncAll();
        TaxSync::run();
    }

    /**
     * Re-derive one transaction's VAT/withholding from its stored lines (each line's
     * net is kept; only the amounts that follow from a rate change).
     */
    public static function reapplyRates(Transaction $t): void
    {
        $withheldByPosition = $t->withheldLines->keyBy('position');
        $lines = $t->vatLines->sortBy('position')->map(fn ($line): array => [
            'amount' => $line->net,
            'vat_rate_id' => $line->vat_rate_id,
            'withheld_rate_id' => $withheldByPosition->get($line->position)?->withheld_rate_id,
        ])->values()->all();

        $resolved = AmountLines::resolve($lines, 'net', $t->invoice_date);

        if ((float) $t->vat_amount === $resolved['vat_amount'] && (float) $t->withheld_amount === $resolved['withheld_amount']) {
            return;
        }

        $t->update([
            'net' => $resolved['net'],
            'vat_amount' => $resolved['vat_amount'],
            'withheld_amount' => $resolved['withheld_amount'],
        ]);
        $t->vatLines()->delete();
        $t->vatLines()->createMany($resolved['vat_lines']);
        $t->withheldLines()->delete();
        $t->withheldLines()->createMany($resolved['withheld_lines']);
    }
}

<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The monthly withholding-tax remittance ledger — derived live, never stored.
 *
 * Much simpler than VAT (no credit rollover, no installments): withholding kept
 * back on expense transactions in a month is remitted to the state on the **last
 * working day of the following month**. Like every tax it is attributed by
 * **`invoice_date`**, not payment date. Income-side withholding, if ever recorded,
 * is the client's liability and is not summed here.
 *
 * Only months that actually have withholding are emitted (each is an independent
 * bucket — nothing carries between them).
 */
class WithheldLedger
{
    /**
     * @return list<array{month: string, withheld: float, due_date: string}>
     */
    public static function monthly(): array
    {
        /** @var array<string, float> $byMonth */
        $byMonth = [];

        Transaction::query()
            ->where('type', 'expense')
            ->where('withheld_amount', '>', 0)
            ->get(['invoice_date', 'withheld_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->invoice_date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0.0) + (float) $t->withheld_amount;
            });

        if ($byMonth === []) {
            return [];
        }

        $keys = array_keys($byMonth);
        sort($keys);

        $holidays = WorkingDays::holidaySet();
        $rows = [];

        foreach ($keys as $key) {
            $due = Carbon::createFromFormat('Y-m-d', $key.'-01')->addMonth();
            $rows[] = [
                'month' => $key,
                'withheld' => round($byMonth[$key], 2),
                'due_date' => WorkingDays::lastWorkingDay($due->year, $due->month, $holidays)->format('Y-m-d'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        $out = [];
        foreach (self::monthly() as $row) {
            if ($row['withheld'] > 0) {
                $out[] = new TaxObligation('withheld', $row['month'], $row['withheld'], $row['due_date']);
            }
        }

        return $out;
    }
}

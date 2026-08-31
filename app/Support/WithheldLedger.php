<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The monthly withholding-tax remittance ledger — derived live, never stored.
 *
 * Much simpler than VAT (no credit rollover, no installments): withholding kept
 * back on expense transactions in a month is remitted to the state by the end of
 * the **next** month. Unlike VAT, it is attributed to the transaction's **payment
 * date** (`date`), not `invoice_date` — it's due when the contractor is actually
 * paid. Income-side withholding, if ever recorded, is the client's liability and
 * is not summed here.
 *
 * Every calendar month between the earliest withholding and the later of (latest
 * transaction, today) is emitted so last month's collection always has a row to be
 * payable in.
 */
class WithheldLedger
{
    /**
     * @return list<array{month: string, withheld: float, payable_this_month: float}>
     */
    public static function monthly(): array
    {
        /** @var array<string, float> $byMonth */
        $byMonth = [];

        Transaction::query()
            ->where('type', 'expense')
            ->where('withheld_amount', '>', 0)
            ->get(['date', 'withheld_amount'])
            ->each(function (Transaction $t) use (&$byMonth): void {
                $key = substr((string) $t->date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0.0) + (float) $t->withheld_amount;
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
        $previous = 0.0;

        for ($m = $start->copy(); $m->lessThanOrEqualTo($end); $m->addMonth()) {
            $key = $m->format('Y-m');
            $withheld = round($byMonth[$key] ?? 0.0, 2);

            $rows[] = [
                'month' => $key,
                'withheld' => $withheld,
                'payable_this_month' => $previous,
            ];

            $previous = $withheld;
        }

        return $rows;
    }
}

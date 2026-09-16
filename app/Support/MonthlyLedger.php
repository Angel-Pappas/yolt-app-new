<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Shared shape for a "simple" monthly tax (withholding, FMY, EFKA): an amount summed
 * per invoice month, where each month is its own independent bucket paid on the last
 * working day of the following month — no credit rollover, no installments. (VAT and
 * income tax are different and have their own ledgers.)
 */
class MonthlyLedger
{
    /**
     * @param  array<string, float>  $byMonth  invoice-month 'YYYY-MM' => summed amount
     * @return array{
     *     rows: list<array{month: string, amount: float, due_date: string}>,
     *     obligations: list<TaxObligation>,
     * }
     */
    public static function build(string $tax, array $byMonth): array
    {
        if ($byMonth === []) {
            return ['rows' => [], 'obligations' => []];
        }

        $keys = array_keys($byMonth);
        sort($keys);
        $holidays = WorkingDays::holidaySet();

        $rows = [];
        $obligations = [];

        foreach ($keys as $key) {
            $amount = round($byMonth[$key], 2);
            $due = Carbon::createFromFormat('Y-m-d', $key.'-01')->addMonth();
            $dueDate = WorkingDays::lastWorkingDay($due->year, $due->month, $holidays)->format('Y-m-d');

            $rows[] = ['month' => $key, 'amount' => $amount, 'due_date' => $dueDate];
            if ($amount > 0) {
                $obligations[] = new TaxObligation($tax, $key, $amount, $dueDate);
            }
        }

        return ['rows' => $rows, 'obligations' => $obligations];
    }
}

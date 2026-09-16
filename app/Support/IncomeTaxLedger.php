<?php

namespace App\Support;

use App\Models\IncomeTaxYear;
use Illuminate\Support\Carbon;

/**
 * Income tax installments — derived from the stored {@see IncomeTaxYear} records. Each
 * year contributes one installment per month across its window (first → last
 * installment month), each equal to the flat monthly amount and due on **that month's
 * own last working day** (unlike the monthly taxes, income tax is paid in the month it
 * is listed, not the following one).
 */
class IncomeTaxLedger
{
    /**
     * The full installment schedule, oldest first.
     *
     * @return list<array{year: int, month: string, amount: float, due_date: string}>
     */
    public static function schedule(): array
    {
        $holidays = WorkingDays::holidaySet();
        $rows = [];

        foreach (IncomeTaxYear::query()->orderBy('year')->get() as $record) {
            $amount = round((float) $record->monthly_installment_amount, 2);
            $start = Carbon::parse($record->first_installment_month)->startOfMonth();
            $end = Carbon::parse($record->last_installment_month)->startOfMonth();

            for ($m = $start->copy(); $m->lessThanOrEqualTo($end); $m->addMonth()) {
                $rows[] = [
                    'year' => (int) $record->year,
                    'month' => $m->format('Y-m'),
                    'amount' => $amount,
                    'due_date' => WorkingDays::lastWorkingDay($m->year, $m->month, $holidays)->format('Y-m-d'),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['month'], $b['month']));

        return $rows;
    }

    /**
     * @return list<TaxObligation>
     */
    public static function obligations(): array
    {
        $out = [];
        foreach (self::schedule() as $row) {
            if ($row['amount'] > 0) {
                $out[] = new TaxObligation('income', $row['month'], $row['amount'], $row['due_date']);
            }
        }

        return $out;
    }
}

<?php

namespace App\Support;

use App\Models\IncomeTaxYear;
use Illuminate\Support\Carbon;

/**
 * Income tax installments — derived from the stored {@see IncomeTaxYear} records. Each
 * year contributes one installment per month across its window (first → last
 * installment month), each equal to the flat monthly amount and due on **that month's
 * own last working day** (unlike the monthly taxes, income tax is paid in the month it
 * is listed, not the following one). A fiscal year with no record yet contributes
 * the installments of its {@see IncomeTaxEstimate} instead, marked `estimated`.
 */
class IncomeTaxLedger
{
    /**
     * The full installment schedule, oldest first.
     *
     * @return list<array{year: int, month: string, amount: float, due_date: string, estimated: bool}>
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
                    'estimated' => false,
                ];
            }
        }

        // Fiscal years without an entered figure: the estimate's installments.
        foreach (IncomeTaxEstimate::years() as $estimate) {
            foreach ($estimate['installments'] as $installment) {
                $rows[] = [
                    'year' => $estimate['year'],
                    'month' => $installment['month'],
                    'amount' => $installment['amount'],
                    'due_date' => $installment['due_date'],
                    'estimated' => true,
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
        // One payment per month: an entered year's last installments and the next
        // year's estimate can fall in the same month.
        $byMonth = [];
        foreach (self::schedule() as $row) {
            if ($row['amount'] > 0) {
                $byMonth[$row['month']] ??= ['amount' => 0.0, 'due_date' => $row['due_date']];
                $byMonth[$row['month']]['amount'] += $row['amount'];
            }
        }

        $out = [];
        foreach ($byMonth as $month => $row) {
            $out[] = new TaxObligation('income', $month, round($row['amount'], 2), $row['due_date']);
        }

        return $out;
    }
}

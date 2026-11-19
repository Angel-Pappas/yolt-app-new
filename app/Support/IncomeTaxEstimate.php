<?php

namespace App\Support;

use App\Models\IncomeTaxYear;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The company's income tax, **estimated** from the transactions for every fiscal
 * year the accountant's real figure isn't entered yet — so a deal agreed today
 * already shows the tax it will cost next year.
 *
 * For a fiscal year F (by `invoice_date`, counted rows only):
 *
 *     profit   = income net − expense net − employer EFKA
 *     tax      = rate × max(profit, 0) + business levy
 *     advance  = advance rate × rate × max(profit, 0)        (toward F+1)
 *     credit   = the advance already paid toward F           (from F−1)
 *     payable  = max(tax + advance − credit, 0)
 *
 * paid in equal monthly installments from the first installment month of F+1, each
 * due on that month's last working day. Left out of the profit: tax payments,
 * transfers, anything with a shareholder (capital in, dividends out), and
 * categories switched off under "counts toward profit". Rates come from the tax
 * rules in force at the end of F. Losses are not carried forward.
 *
 * A fiscal year is estimated when no entered {@see IncomeTaxYear} has its first
 * installment in F+1 — entering the real figure replaces the estimate. Years covered:
 * last year, this year and next year (as far as the planned transactions reach); a
 * year with no transactions that count toward profit is not estimated at all.
 */
class IncomeTaxEstimate
{
    /**
     * @return list<array{year: int, income: float, expenses: float, profit: float, tax: float, advance: float, credit: float, payable: float, installments: list<array{month: string, amount: float, due_date: string}>}>
     */
    public static function years(): array
    {
        $current = Carbon::today()->year;
        $records = IncomeTaxYear::query()->get();
        $paidInYear = $records->keyBy(fn (IncomeTaxYear $r): int => Carbon::parse($r->first_installment_month)->year);

        $profits = self::profitByYear();
        $holidays = WorkingDays::holidaySet();
        $out = [];
        $previousAdvance = null; // the advance the previous estimated year put toward this one

        foreach ([$current - 1, $current, $current + 1] as $year) {
            // The advance already paid toward this year: from the year before —
            // its estimate, else the figure entered on its record.
            $credit = $previousAdvance
                ?? (float) $paidInYear->get($year)?->advance_amount;

            if ($paidInYear->has($year + 1)) {
                $previousAdvance = null; // the real figure is in; its advance is on the record

                continue;
            }

            // A year with nothing in it yet has nothing to estimate (no levy-only
            // payments for an empty year).
            if (! isset($profits[$year])) {
                $previousAdvance = null;

                continue;
            }

            $rules = TaxRules::at(sprintf('%d-12-31', $year));
            $income = round($profits[$year]['income'], 2);
            $expenses = round($profits[$year]['expenses'], 2);
            $profit = round($income - $expenses, 2);
            $base = max($profit, 0.0) * $rules->number('company_tax.rate') / 100;
            $tax = round($base + $rules->number('company_tax.business_levy'), 2);
            $advance = round($base * $rules->number('company_tax.advance_rate') / 100, 2);
            $payable = max(round($tax + $advance - $credit, 2), 0.0);

            $out[] = [
                'year' => $year,
                'income' => $income,
                'expenses' => $expenses,
                'profit' => $profit,
                'tax' => $tax,
                'advance' => $advance,
                'credit' => round($credit, 2),
                'payable' => $payable,
                'installments' => self::installments($year + 1, $payable, $rules, $holidays),
            ];
            $previousAdvance = $advance;
        }

        return $out;
    }

    /**
     * Each fiscal year's revenue and deductible cost.
     *
     * @return array<int, array{income: float, expenses: float}>
     */
    public static function profitByYear(): array
    {
        $byYear = [];

        Books::rows(withTaxes: false)
            ->filter(fn (Transaction $t): bool => in_array($t->type, ['income', 'expense'], true)
                && $t->source !== 'tax'
                && ($t->category_id === null || (bool) $t->category?->counts_for_profit)
                && ($t->entity_id === null || $t->entity?->type !== 'shareholder'))
            ->each(function (Transaction $t) use (&$byYear): void {
                $year = $t->invoice_date->year;
                $byYear[$year] ??= ['income' => 0.0, 'expenses' => 0.0];
                if ($t->type === 'income') {
                    $byYear[$year]['income'] += (float) $t->net;
                } else {
                    $byYear[$year]['expenses'] += (float) $t->net + (float) $t->efka_employer_amount;
                }
            });

        return $byYear;
    }

    /**
     * Equal monthly installments (the last takes the rounding remainder).
     *
     * @param  array<string, true>  $holidays
     * @return list<array{month: string, amount: float, due_date: string}>
     */
    private static function installments(int $paymentYear, float $payable, TaxRuleSet $rules, array $holidays): array
    {
        $count = max(1, (int) $rules->number('company_tax.installments'));
        $firstMonth = min(12, max(1, (int) $rules->number('company_tax.first_installment_month')));
        if ($payable <= 0) {
            return [];
        }

        $each = round($payable / $count, 2);
        $out = [];
        $month = Carbon::create($paymentYear, $firstMonth, 1);
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'month' => $month->format('Y-m'),
                'amount' => $i === $count - 1 ? round($payable - $each * ($count - 1), 2) : $each,
                'due_date' => WorkingDays::lastWorkingDay($month->year, $month->month, $holidays)->format('Y-m-d'),
            ];
            $month = $month->copy()->addMonth();
        }

        return $out;
    }
}

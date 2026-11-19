<?php

namespace App\Support;

use App\Models\EmployeeProfile;
use Carbon\CarbonInterface;

/**
 * Greek payroll for one payment of a salaried employee, from the **gross** amount:
 *
 *  1. Social insurance (EFKA): employee and employer shares are a percentage of the
 *     gross, up to the monthly cap on insurable pay.
 *  2. Income tax withheld (FMY): the taxable pay (gross − employee EFKA) is
 *     annualised (× 14 salaries), taxed on the scale for the employee's children and
 *     age, reduced by the tax reduction, and divided back (÷ 14).
 *
 * A bonus (Easter, Christmas, leave allowance) is calculated the same way on its own
 * amount. All figures come from the tax rules in force on the payment date
 * ({@see TaxRules}); the employee's children and birth year from their profile. The
 * forms preview it through `GET /payroll/preview`, so there is no client copy to keep
 * in step.
 */
final class PayrollCalculator
{
    /**
     * @return array{gross: float, efka_employee: float, efka_employer: float, fmy: float, to_pay: float, total_cost: float}
     */
    public static function fromGross(float $gross, int $children, ?int $birthYear, CarbonInterface|string $date): array
    {
        $rules = TaxRules::at($date);
        $year = (int) substr(is_string($date) ? $date : $date->format('Y-m-d'), 0, 4);

        $insurable = min($gross, $rules->number('efka.monthly_cap') ?: $gross);
        $efkaEmployee = round($insurable * $rules->number('efka.employee_rate') / 100, 2);
        $efkaEmployer = round($insurable * $rules->number('efka.employer_rate') / 100, 2);

        $salaries = $rules->number('payroll_tax.salaries_per_year') ?: 14.0;
        $annual = ($gross - $efkaEmployee) * $salaries;
        $age = $birthYear !== null ? $year - $birthYear : null;
        $tax = self::annualTax($annual, $children, $age, $rules);
        $fmy = round($tax / $salaries, 2);

        return [
            'gross' => round($gross, 2),
            'efka_employee' => $efkaEmployee,
            'efka_employer' => $efkaEmployer,
            'fmy' => $fmy,
            'to_pay' => round($gross - $efkaEmployee - $fmy, 2),
            'total_cost' => round($gross + $efkaEmployer, 2),
        ];
    }

    /**
     * The same, for an employee's stored profile (none = no children, no age band).
     *
     * @return array{gross: float, efka_employee: float, efka_employer: float, fmy: float, to_pay: float, total_cost: float}
     */
    public static function forEmployee(float $gross, ?EmployeeProfile $profile, CarbonInterface|string $date): array
    {
        return self::fromGross(
            $gross,
            $profile !== null ? $profile->children : 0,
            $profile?->birth_date?->year,
            $date,
        );
    }

    /**
     * The year's income tax on an annual taxable income: the scale (by children,
     * with the young-employee rate where it is lower) minus the tax reduction, never
     * below zero.
     */
    public static function annualTax(float $annual, int $children, ?int $age, TaxRuleSet $rules): float
    {
        if ($annual <= 0) {
            return 0.0;
        }

        $band = null;
        if ($age !== null) {
            foreach ($rules->ageBands() as $candidate) {
                if ($age <= $candidate['max_age']) {
                    $band = $candidate;
                    break;
                }
            }
        }

        $tax = 0.0;
        $lower = 0.0;
        foreach ($rules->brackets() as $bracket) {
            $upper = $bracket['up_to'] ?? INF;
            if ($annual <= $lower) {
                break;
            }
            $rates = $bracket['rates'];
            $rate = $rates[min($children, count($rates) - 1)] ?? 0.0;

            // Split the bracket where the young-employee band ends, so the lower
            // rate covers exactly the income up to the band's limit.
            $segments = [[$lower, min($upper, $annual), $rate]];
            if ($band !== null && $lower < $band['up_to']) {
                $cut = min($upper, $band['up_to']);
                $segments = [[$lower, min($cut, $annual), min($rate, $band['rate'])]];
                if ($annual > $cut && $upper > $cut) {
                    $segments[] = [$cut, min($upper, $annual), $rate];
                }
            }
            foreach ($segments as [$from, $to, $r]) {
                if ($to > $from) {
                    $tax += ($to - $from) * $r / 100;
                }
            }
            $lower = $upper;
        }

        return max(0.0, $tax - self::reduction($annual, $children, $rules));
    }

    /**
     * The tax reduction: an amount by number of children, cut by a fixed sum per
     * €1.000 of income above the threshold (not cut for large families).
     */
    public static function reduction(float $annual, int $children, TaxRuleSet $rules): float
    {
        $table = array_values((array) data_get($rules->data, 'payroll_tax.reduction.by_children', []));
        if ($table === []) {
            return 0.0;
        }
        $last = count($table) - 1;
        $amount = (float) $table[min($children, $last)]
            + max(0, $children - $last) * $rules->number('payroll_tax.reduction.extra_per_child');

        $noCutFrom = (int) $rules->number('payroll_tax.reduction.no_cut_from_children');
        $threshold = $rules->number('payroll_tax.reduction.threshold');
        if ($annual > $threshold && ! ($noCutFrom > 0 && $children >= $noCutFrom)) {
            $amount -= ($annual - $threshold) / 1000 * $rules->number('payroll_tax.reduction.cut_per_1000');
        }

        return max(0.0, $amount);
    }
}

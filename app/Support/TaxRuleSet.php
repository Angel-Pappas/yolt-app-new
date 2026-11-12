<?php

namespace App\Support;

/**
 * One dated set of tax-law figures, read-only. `data` has this shape (all money in
 * euros, all rates in %):
 *
 *     efka:        employee_rate, employer_rate, monthly_cap
 *     payroll_tax: salaries_per_year,
 *                  brackets[]  { up_to|null, rates[5] }   (children 0,1,2,3,4+)
 *                  age_bands[] { max_age, up_to, rate }
 *                  reduction   { by_children[5], extra_per_child, threshold,
 *                                cut_per_1000, no_cut_from_children }
 *     company_tax: rate, advance_rate, business_levy, installments,
 *                  first_installment_month
 *     severance:   min_daily_wage
 */
final class TaxRuleSet
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $validFrom,
        public readonly array $data,
    ) {}

    public function number(string $path): float
    {
        return (float) data_get($this->data, $path, 0);
    }

    /** @return list<array{up_to: float|null, rates: list<float>}> */
    public function brackets(): array
    {
        return array_values(array_map(fn (array $b): array => [
            'up_to' => $b['up_to'] === null ? null : (float) $b['up_to'],
            'rates' => array_map('floatval', array_values($b['rates'])),
        ], (array) data_get($this->data, 'payroll_tax.brackets', [])));
    }

    /** @return list<array{max_age: int, up_to: float, rate: float}> */
    public function ageBands(): array
    {
        $bands = array_values(array_map(fn (array $b): array => [
            'max_age' => (int) $b['max_age'],
            'up_to' => (float) $b['up_to'],
            'rate' => (float) $b['rate'],
        ], (array) data_get($this->data, 'payroll_tax.age_bands', [])));
        usort($bands, fn (array $a, array $b): int => $a['max_age'] <=> $b['max_age']);

        return $bands;
    }
}

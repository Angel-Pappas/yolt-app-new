<?php

namespace App\Support;

/**
 * The tax-law figures the app starts with. Each set applies from its date until the
 * next one; later changes are made in Configuration ▸ Tax rules (a new dated set),
 * never here. The shape is documented on {@see TaxRuleSet}.
 *
 * Sources (checked 2026-11-12):
 *  - Payroll tax scale from 1/1/2026 (Law 5246/2025): 9 / 20 / 26 / 34 / 39 / 44 %,
 *    lower second and third brackets per dependent child, 0 % up to €20.000 for
 *    employees up to 25 and 9 % for those 26–30; tax reduction €777–1.340 by children,
 *    cut by €20 per €1.000 of income above €12.000 —
 *    https://krs.gr/2026/05/14/φμυ-2026-τι-είναι-τι-ισχύει-με-τις-νέες-κλί/ ,
 *    https://www.eaed.gr/eshop/vivlia/pinakes-parakratisis-fmy-2026/ ,
 *    https://www.capital.gr/oikonomia/3942917/ . The monthly method (taxable pay × 14,
 *    scale, minus reduction, ÷ 14) reproduces two real 2026 payslips to the cent
 *    (920,00 gross → 25,33; 1.462,18 gross, age 27 → 66,69) — see PayrollCalculatorTest.
 *  - Scale up to 31/12/2025: 9 / 22 / 28 / 36 / 44 %, same reductions (the company's
 *    own projection workbook).
 *  - Social insurance 2026: employee 13,37 %, employer 21,79 %, monthly cap
 *    €7.761,94 — https://katharosmisthos.gr/blog/eisfores-efka-2026-odigos ,
 *    https://www.taxheaven.gr/news/72721/ .
 *  - Company (IKE) income tax 22 %, advance 80 %, business levy €1.000 (seat in a
 *    city over 200.000; €800 elsewhere) — https://www.pkp.com.gr/forologia-ike/ ,
 *    https://www.oneaccounting.gr/blog/posts/forologia-ike-2026 . Paid in 8 monthly
 *    installments from July (as the accountant's 2026 schedule in the app).
 *  - Severance cap: pay above 8 × the minimum daily wage (€41,09 from 1/4/2026) × 30
 *    is ignored — https://paymaster.gr/apozimiosi-apolysis/ .
 */
final class TaxRuleDefaults
{
    /**
     * @return array<string, array{note: string, data: array<string, mixed>}>
     */
    public static function sets(): array
    {
        $y2026 = [
            'efka' => [
                'employee_rate' => 13.37,
                'employer_rate' => 21.79,
                'monthly_cap' => 7761.94,
            ],
            'payroll_tax' => [
                'salaries_per_year' => 14,
                // Rate per bracket by dependent children: 0, 1, 2, 3, 4 or more.
                'brackets' => [
                    ['up_to' => 10000, 'rates' => [9, 9, 9, 9, 0]],
                    ['up_to' => 20000, 'rates' => [20, 18, 16, 9, 0]],
                    ['up_to' => 30000, 'rates' => [26, 24, 22, 20, 18]],
                    ['up_to' => 40000, 'rates' => [34, 34, 34, 34, 34]],
                    ['up_to' => 60000, 'rates' => [39, 39, 39, 39, 39]],
                    ['up_to' => null, 'rates' => [44, 44, 44, 44, 44]],
                ],
                // A lower rate on income up to `up_to` for young employees (by the
                // age reached in the tax year).
                'age_bands' => [
                    ['max_age' => 25, 'up_to' => 20000, 'rate' => 0],
                    ['max_age' => 30, 'up_to' => 20000, 'rate' => 9],
                ],
                'reduction' => [
                    'by_children' => [777, 810, 900, 1120, 1340],
                    'extra_per_child' => 220,
                    'threshold' => 12000,
                    'cut_per_1000' => 20,
                    'no_cut_from_children' => 5,
                ],
            ],
            'company_tax' => [
                'rate' => 22,
                'advance_rate' => 80,
                'business_levy' => 1000,
                'installments' => 8,
                'first_installment_month' => 7,
            ],
            'severance' => [
                'min_daily_wage' => 41.09,
            ],
        ];

        $y2025 = $y2026;
        $y2025['payroll_tax']['brackets'] = [
            ['up_to' => 10000, 'rates' => [9, 9, 9, 9, 9]],
            ['up_to' => 20000, 'rates' => [22, 22, 22, 22, 22]],
            ['up_to' => 30000, 'rates' => [28, 28, 28, 28, 28]],
            ['up_to' => 40000, 'rates' => [36, 36, 36, 36, 36]],
            ['up_to' => null, 'rates' => [44, 44, 44, 44, 44]],
        ];
        $y2025['payroll_tax']['age_bands'] = [];
        // The insurance cap and minimum wage of 2025 were not looked up: the 2026
        // figures stand in (no salary here is near the cap, and severance is only
        // ever calculated for a date from today on).

        return [
            '2025-01-01' => ['note' => 'Law in force during 2025', 'data' => $y2025],
            '2026-01-01' => ['note' => 'Law 5246/2025 — new payroll tax scale', 'data' => $y2026],
        ];
    }
}

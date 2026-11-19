<?php

use App\Models\TaxRule;
use App\Support\PayrollCalculator;
use App\Support\TaxRules;

// The first two cases are real 2026 payslip figures from the company's own
// projection workbook (gross → FMY), reproduced to the cent.

test('minimum-wage salary in 2026: 920,00 gross → FMY 25,33', function () {
    $r = PayrollCalculator::fromGross(920.00, 0, 1984, '2026-09-30');

    expect($r['efka_employee'])->toBe(123.00);   // 13,37 %
    expect($r['efka_employer'])->toBe(200.47);   // 21,79 %
    expect($r['fmy'])->toBe(25.33);
    expect($r['to_pay'])->toBe(771.67);
    expect($r['total_cost'])->toBe(1120.47);
});

test('an employee aged 26–30 in 2026: 1.462,18 gross → FMY 66,69', function () {
    $r = PayrollCalculator::fromGross(1462.18, 0, 1999, '2026-05-29');

    expect($r['efka_employee'])->toBe(195.49);
    expect($r['fmy'])->toBe(66.69);
    expect($r['to_pay'])->toBe(1200.00);
});

test('an employee up to 25 pays no tax on income up to 20.000', function () {
    // 1.200 gross → taxable 1.039,56 × 14 = 14.553,84 a year, all at 0 %.
    expect(PayrollCalculator::fromGross(1200, 0, 2003, '2026-06-30')['fmy'])->toBe(0.0);
});

test('above the young-employee limit the normal scale applies', function () {
    // 30.000 a year at age 24: 0 % to 20.000, then 26 % on 10.000 = 2.600;
    // reduction 777 − 20 × 18 = 417 → 2.183.
    $rules = TaxRules::at('2026-06-30');

    expect(round(PayrollCalculator::annualTax(30000, 0, 24, $rules), 2))->toBe(2183.0);
    // The same income with no age band: 900 + 2.000 + 2.600 = 5.500 − 417.
    expect(round(PayrollCalculator::annualTax(30000, 0, 40, $rules), 2))->toBe(5083.0);
});

test('children lower the scale and raise the reduction', function () {
    $rules = TaxRules::at('2026-06-30');

    // 18.000 a year, 2 children: 900 + 8.000 × 16 % = 2.180; reduction 900 − 120.
    expect(round(PayrollCalculator::annualTax(18000, 2, 40, $rules), 2))->toBe(1400.0);
    // 4 children: 0 % up to 20.000.
    expect(PayrollCalculator::annualTax(18000, 4, 40, $rules))->toBe(0.0);
});

test('the reduction is cut by 20 per 1.000 above 12.000 and never goes below zero', function () {
    $rules = TaxRules::at('2026-06-30');

    expect(PayrollCalculator::reduction(12000, 0, $rules))->toBe(777.0);
    expect(PayrollCalculator::reduction(13000, 0, $rules))->toBe(757.0);
    expect(PayrollCalculator::reduction(80000, 0, $rules))->toBe(0.0);
    expect(PayrollCalculator::reduction(13000, 1, $rules))->toBe(790.0);
    // Five children: 1.340 + 220, and no cut.
    expect(PayrollCalculator::reduction(50000, 5, $rules))->toBe(1560.0);
});

test('tax never goes below zero', function () {
    expect(PayrollCalculator::fromGross(400, 0, 1980, '2026-06-30')['fmy'])->toBe(0.0);
});

test('insurance stops at the monthly cap', function () {
    $r = PayrollCalculator::fromGross(10000, 0, 1980, '2026-06-30');

    expect($r['efka_employee'])->toBe(round(7761.94 * 0.1337, 2));
    expect($r['efka_employer'])->toBe(round(7761.94 * 0.2179, 2));
});

test('the rules in force on the payment date are used', function () {
    // 2025 scale: second bracket 22 %. 920 gross → taxable 11.158 a year:
    // 900 + 1.158 × 22 % = 1.154,76 − 777 = 377,76 → 26,98 a month.
    expect(PayrollCalculator::fromGross(920.00, 0, 1984, '2025-11-28')['fmy'])->toBe(26.98);
    expect(PayrollCalculator::fromGross(920.00, 0, 1984, '2026-01-30')['fmy'])->toBe(25.33);
});

test('a new dated set of rules takes over from its date', function () {
    $data = TaxRule::query()->orderByDesc('valid_from')->first()->data;
    $data['efka']['employee_rate'] = 10;
    TaxRule::create(['valid_from' => '2027-01-01', 'data' => $data]);

    expect(PayrollCalculator::fromGross(1000, 0, 1980, '2026-12-31')['efka_employee'])->toBe(133.70);
    expect(PayrollCalculator::fromGross(1000, 0, 1980, '2027-01-01')['efka_employee'])->toBe(100.00);
});

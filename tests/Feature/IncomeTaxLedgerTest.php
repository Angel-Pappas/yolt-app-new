<?php

use App\Models\IncomeTaxYear;
use App\Support\IncomeTaxLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-16');
});

test('the schedule generates one installment per month in the window', function () {
    IncomeTaxYear::factory()->create([
        'year' => 2025,
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2027-01-01',
        'monthly_installment_amount' => 2750,
    ]);

    $schedule = IncomeTaxLedger::schedule();

    // June 2026 through January 2027 inclusive = 8 months.
    expect($schedule)->toHaveCount(8);
    expect($schedule[0]['month'])->toBe('2026-06');
    expect($schedule[0]['amount'])->toBe(2750.0);
    expect(end($schedule)['month'])->toBe('2027-01');
});

test('each income tax installment is due on its own month last working day', function () {
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-10-01',
        'last_installment_month' => '2026-10-01',
        'monthly_installment_amount' => 1000,
    ]);

    $schedule = IncomeTaxLedger::schedule();

    expect($schedule)->toHaveCount(1);
    // Oct 2026 ends Sat 31 -> last working day Fri 30 of the SAME month (not M+1).
    expect($schedule[0]['due_date'])->toBe('2026-10-30');
});

test('obligations mirror the schedule', function () {
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-07-01',
        'monthly_installment_amount' => 500,
    ]);

    $obligations = IncomeTaxLedger::obligations();

    expect($obligations)->toHaveCount(2);
    expect($obligations[0]->tax)->toBe('income');
    expect($obligations[0]->period)->toBe('2026-06');
    expect($obligations[0]->amount)->toBe(500.0);
});

test('a zero monthly amount produces no obligations', function () {
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-07-01',
        'monthly_installment_amount' => 0,
    ]);

    expect(IncomeTaxLedger::obligations())->toBeEmpty();
});

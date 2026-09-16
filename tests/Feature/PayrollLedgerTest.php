<?php

use App\Models\Transaction;
use App\Support\EfkaLedger;
use App\Support\FmyLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-16');
});

test('the FMY ledger sums fmy_amount by invoice month, due the following month', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'fmy_amount' => 80, 'invoice_date' => '2026-04-10',
    ]);
    Transaction::factory()->create([
        'type' => 'expense', 'fmy_amount' => 20, 'invoice_date' => '2026-04-25',
    ]);

    $apr = collect(FmyLedger::monthly())->firstWhere('month', '2026-04');
    expect($apr['amount'])->toBe(100.0);
    expect($apr['due_date'])->toStartWith('2026-05');

    $obligations = FmyLedger::obligations();
    expect($obligations)->toHaveCount(1);
    expect($obligations[0]->tax)->toBe('fmy');
    expect($obligations[0]->period)->toBe('2026-04');
    expect($obligations[0]->amount)->toBe(100.0);
});

test('the EFKA ledger sums employee and employer amounts into one bucket', function () {
    Transaction::factory()->create([
        'type' => 'expense',
        'efka_employee_amount' => 150,
        'efka_employer_amount' => 250,
        'invoice_date' => '2026-04-10',
    ]);

    $apr = collect(EfkaLedger::monthly())->firstWhere('month', '2026-04');
    expect($apr['amount'])->toBe(400.0);
    expect($apr['due_date'])->toStartWith('2026-05');

    $obligations = EfkaLedger::obligations();
    expect($obligations)->toHaveCount(1);
    expect($obligations[0]->tax)->toBe('efka');
    expect($obligations[0]->amount)->toBe(400.0);
});

test('a transaction with no payroll amounts produces no FMY or EFKA rows', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'invoice_date' => '2026-04-10',
    ]);

    expect(FmyLedger::monthly())->toBeEmpty();
    expect(EfkaLedger::monthly())->toBeEmpty();
});

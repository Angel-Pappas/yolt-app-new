<?php

use App\Models\Transaction;
use App\Support\WithheldLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-16');
});

test('withholding is attributed by invoice date, not payment date', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 50,
        'date' => '2026-03-31', 'invoice_date' => '2026-04-10',
    ]);

    $rows = collect(WithheldLedger::monthly());

    // Keyed off the invoice month (April), not the payment month (March).
    expect($rows->firstWhere('month', '2026-03'))->toBeNull();
    $apr = $rows->firstWhere('month', '2026-04');
    expect($apr['amount'])->toBe(50.0);
    expect($apr['due_date'])->toStartWith('2026-05');
});

test('withholding obligations are due the following month', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 50, 'invoice_date' => '2026-04-10',
    ]);

    $obligations = WithheldLedger::obligations();

    expect($obligations)->toHaveCount(1);
    expect($obligations[0]->tax)->toBe('withheld');
    expect($obligations[0]->period)->toBe('2026-04');
    expect($obligations[0]->amount)->toBe(50.0);
});

test('several withholding transactions in a month sum into one bucket', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 30, 'invoice_date' => '2026-04-05',
    ]);
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 20, 'invoice_date' => '2026-04-25',
    ]);

    $apr = collect(WithheldLedger::monthly())->firstWhere('month', '2026-04');

    expect($apr['amount'])->toBe(50.0);
});

test('income-side withholding is not counted', function () {
    Transaction::factory()->create([
        'type' => 'income', 'withheld_amount' => 30, 'invoice_date' => '2026-06-15',
    ]);

    expect(WithheldLedger::monthly())->toBeEmpty();
});

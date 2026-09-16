<?php

use App\Models\PublicHoliday;
use App\Models\Transaction;
use App\Support\VatLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-16');
});

test('a single income month is payable in full on the last working day of the next month', function () {
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);

    $row = collect(VatLedger::monthly())->firstWhere('month', '2026-01');

    expect($row['income_vat'])->toBe(100.0);
    expect($row['net'])->toBe(100.0);
    expect($row['payable'])->toBe(100.0);
    // Feb 2026 ends on Sat 28 -> last working day is Fri 27.
    expect($row['due_date'])->toBe('2026-02-27');
});

test('a credit rolls forward to offset a later debit', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 100, 'invoice_date' => '2026-02-15',
    ]);

    $rows = collect(VatLedger::monthly());
    $jan = $rows->firstWhere('month', '2026-01');
    $feb = $rows->firstWhere('month', '2026-02');

    expect($jan['net'])->toBe(-100.0);
    expect($jan['payable'])->toBe(0.0);
    expect($feb['rollover_in'])->toBe(100.0);
    expect($feb['net'])->toBe(100.0);
    expect($feb['payable'])->toBe(0.0);
});

test('a debit is paid in full with no installment split', function () {
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 500, 'invoice_date' => '2026-03-15',
    ]);

    $row = collect(VatLedger::monthly())->firstWhere('month', '2026-03');

    expect($row['payable'])->toBe(500.0);
    expect($row)->not->toHaveKey('payable_next_month');
    expect($row['due_date'])->toStartWith('2026-04');
});

test('obligations lists one payment per positive-payable month', function () {
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 200, 'invoice_date' => '2026-05-15',
    ]);

    $obligations = VatLedger::obligations();

    expect($obligations)->toHaveCount(1);
    expect($obligations[0]->tax)->toBe('vat');
    expect($obligations[0]->period)->toBe('2026-05');
    expect($obligations[0]->amount)->toBe(200.0);
    expect($obligations[0]->dueDate)->toStartWith('2026-06');
});

test('a credit-only history produces no obligations', function () {
    Transaction::factory()->create([
        'type' => 'expense', 'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);

    expect(VatLedger::obligations())->toBeEmpty();
});

test('a public holiday shifts the VAT due date back', function () {
    PublicHoliday::factory()->create(['date' => '2026-02-27']);
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);

    $row = collect(VatLedger::monthly())->firstWhere('month', '2026-01');

    // The 27th (Fri) is now a holiday -> Thu 26.
    expect($row['due_date'])->toBe('2026-02-26');
});

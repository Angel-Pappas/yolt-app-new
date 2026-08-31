<?php

use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\VatLedger;

function vatRow(string $month): ?array
{
    return collect(VatLedger::monthly())->firstWhere('month', $month);
}

test('an empty ledger has no rows', function () {
    expect(VatLedger::monthly())->toBe([]);
});

test('a small debit (<= €100) is payable in full the same month', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);
    Transaction::factory()->create([
        'type' => 'expense', 'wallet_id' => $wallet->id,
        'vat_amount' => 40, 'invoice_date' => '2026-01-20',
    ]);

    $jan = vatRow('2026-01');
    expect($jan['income_vat'])->toBe(100.0);
    expect($jan['expense_vat'])->toBe(40.0);
    expect($jan['net'])->toBe(60.0);
    expect($jan['payable_this_month'])->toBe(60.0);
    expect($jan['payable_next_month'])->toBe(0.0);
});

test('a debit over €100 splits into two equal installments', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'vat_amount' => 300, 'invoice_date' => '2026-02-10',
    ]);

    $feb = vatRow('2026-02');
    expect($feb['net'])->toBe(300.0);
    expect($feb['payable_this_month'])->toBe(150.0);
    expect($feb['payable_next_month'])->toBe(150.0);

    // The deferred half lands on the following month's payable.
    $mar = vatRow('2026-03');
    expect($mar['payable_this_month'])->toBe(150.0);
    expect($mar['payable_next_month'])->toBe(0.0);
});

test('a credit rolls forward to offset a later debit', function () {
    $wallet = Wallet::factory()->create();
    // January is a credit month: only input VAT.
    Transaction::factory()->create([
        'type' => 'expense', 'wallet_id' => $wallet->id,
        'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);
    // February output VAT exactly cancels the carried credit.
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'vat_amount' => 100, 'invoice_date' => '2026-02-15',
    ]);

    $jan = vatRow('2026-01');
    expect($jan['net'])->toBe(-100.0);
    expect($jan['payable_this_month'])->toBe(0.0);

    $feb = vatRow('2026-02');
    expect($feb['rollover_in'])->toBe(100.0);
    expect($feb['net'])->toBe(100.0);
    expect($feb['payable_this_month'])->toBe(0.0);
});

test('gap months are emitted so state passes through them', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'expense', 'wallet_id' => $wallet->id,
        'vat_amount' => 50, 'invoice_date' => '2026-01-10',
    ]);
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'vat_amount' => 50, 'invoice_date' => '2026-04-10',
    ]);

    // February and March have no activity but still appear, carrying the credit.
    expect(vatRow('2026-02'))->not->toBeNull();
    expect(vatRow('2026-03'))->not->toBeNull();
    expect(vatRow('2026-02')['rollover_in'])->toBe(50.0);
    expect(vatRow('2026-04')['payable_this_month'])->toBe(0.0);
});

test('transfers carry no VAT and do not appear', function () {
    $a = Wallet::factory()->create();
    $b = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'transfer', 'wallet_id' => $a->id, 'to_wallet_id' => $b->id,
        'net' => 500, 'vat_amount' => 0, 'invoice_date' => '2026-01-10',
    ]);

    expect(VatLedger::monthly())->toBe([]);
});

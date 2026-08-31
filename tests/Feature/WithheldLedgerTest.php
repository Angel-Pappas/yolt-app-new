<?php

use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\WithheldLedger;

function withheldRow(string $month): ?array
{
    return collect(WithheldLedger::monthly())->firstWhere('month', $month);
}

test('an empty withholding ledger has no rows', function () {
    expect(WithheldLedger::monthly())->toBe([]);
});

test('withholding collected in a month is payable the next month', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'expense', 'wallet_id' => $wallet->id,
        'withheld_amount' => 200, 'date' => '2026-01-15',
    ]);

    $jan = withheldRow('2026-01');
    expect($jan['withheld'])->toBe(200.0);
    expect($jan['payable_this_month'])->toBe(0.0);

    $feb = withheldRow('2026-02');
    expect($feb['withheld'])->toBe(0.0);
    expect($feb['payable_this_month'])->toBe(200.0);
});

test('withholding is keyed by payment date, not invoice date', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'expense', 'wallet_id' => $wallet->id,
        'withheld_amount' => 50,
        'date' => '2026-03-01', 'invoice_date' => '2026-01-01',
    ]);

    expect(withheldRow('2026-03')['withheld'])->toBe(50.0);
    expect(withheldRow('2026-01'))->toBeNull();
});

test('income withholding is not summed', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'withheld_amount' => 90, 'date' => '2026-01-10',
    ]);

    expect(WithheldLedger::monthly())->toBe([]);
});

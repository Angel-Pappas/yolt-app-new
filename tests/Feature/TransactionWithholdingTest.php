<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;

test('an expense can carry withholding computed server-side', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'description' => 'Contractor fee',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '1000', 'vat_rate_id' => $vatRate->id]],
        'withheld_lines' => [['net' => '1000', 'withheld_rate_id' => $withheldRate->id]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->net)->toBe('1000.00');
    expect($transaction->vat_amount)->toBe('240.00');
    expect($transaction->withheld_amount)->toBe('200.00');
    expect($transaction->withheldLines)->toHaveCount(1);
    // Cash total = net + VAT − withheld.
    $total = (float) $transaction->net + (float) $transaction->vat_amount - (float) $transaction->withheld_amount;
    expect($total)->toBe(1040.0);
});

test('withholding is recomputed from the rate regardless of client input', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '500', 'vat_rate_id' => null]],
        'withheld_lines' => [['net' => '500', 'withheld_rate_id' => $withheldRate->id]],
    ])->assertRedirect();

    expect(Transaction::first()->withheld_amount)->toBe('100.00');
});

test('withholding is optional', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '80', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

test('editing away withholding clears the withheld lines', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '300', 'vat_rate_id' => null]],
        'withheld_lines' => [['net' => '300', 'withheld_rate_id' => $withheldRate->id]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('60.00');

    $this->actingAs($user)->patch("/transactions/{$transaction->id}", [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '300', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $transaction->refresh();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

test('a transfer records no withholding', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $from = Wallet::factory()->create();
    $to = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => '250',
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;

test('a finance user can add a transaction with server-computed VAT', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'description' => 'Fuel',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '100', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction)->not->toBeNull();
    expect($transaction->net)->toBe('100.00');
    expect($transaction->vat_amount)->toBe('24.00');
    expect($transaction->wallet_id)->toBe($wallet->id);
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->vat_rate_id)->toBe($vatRate->id);
    expect($transaction->vatLines)->toHaveCount(1);
});

test('VAT is recomputed from the rate regardless of client input', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 13]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '200', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();

    expect(Transaction::first()->vat_amount)->toBe('26.00');
});

test('a transaction with no VAT rate has zero VAT', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '50', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->vat_amount)->toBe('0.00');
    expect($transaction->net)->toBe('50.00');
});

test('adding a transaction requires a wallet and at least one line', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'lines' => [],
    ])->assertSessionHasErrors(['wallet_id', 'lines']);
});

test('a non-finance user cannot add a transaction', function () {
    $wallet = Wallet::factory()->create();

    $this->actingAs(User::factory()->create())->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '10', 'vat_rate_id' => null]],
    ])->assertForbidden();
});

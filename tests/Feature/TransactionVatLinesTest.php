<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;

test('total mode derives net from the gross amount', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);

    // 124 total at 24% -> net 100, VAT 24.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'total',
        'lines' => [['amount' => '124', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->net)->toBe('100.00');
    expect($transaction->vat_amount)->toBe('24.00');
});

test('total mode reconstructs the gross exactly (no rounding drift)', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);

    // A total that does not divide cleanly; net + VAT must still equal the total.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'total',
        'lines' => [['amount' => '100', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    $sum = (float) $transaction->net + (float) $transaction->vat_amount;
    expect($sum)->toBe(100.0);
});

test('a multi-rate transaction sums the lines and stores no single rate', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $high = VatRate::factory()->create(['rate' => 24]);
    $low = VatRate::factory()->create(['rate' => 6]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [
            ['amount' => '1000', 'vat_rate_id' => $high->id],
            ['amount' => '200', 'vat_rate_id' => $low->id],
        ],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->net)->toBe('1200.00');
    expect($transaction->vat_amount)->toBe('252.00'); // 240 + 12
    expect($transaction->vat_rate_id)->toBeNull(); // mixed rates
    expect($transaction->vatLines)->toHaveCount(2);
});

test('a single-line transaction keeps its denormalized rate', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '500', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();

    expect(Transaction::first()->vat_rate_id)->toBe($vatRate->id);
});

test('amount_mode is required for income and expense', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['amount' => '10', 'vat_rate_id' => null]],
    ])->assertSessionHasErrors('amount_mode');
});

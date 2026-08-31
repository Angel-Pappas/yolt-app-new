<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;

test('a finance user can create a transfer between two wallets', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $from = Wallet::factory()->create();
    $to = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => '500',
    ])->assertRedirect();

    $transfer = Transaction::first();
    expect($transfer->type)->toBe('transfer');
    expect($transfer->net)->toBe('500.00');
    expect($transfer->vat_amount)->toBe('0.00');
    expect($transfer->wallet_id)->toBe($from->id);
    expect($transfer->to_wallet_id)->toBe($to->id);
    expect($transfer->vatLines)->toHaveCount(0);
});

test('a transfer must go to a different wallet', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'to_wallet_id' => $wallet->id,
        'net' => '100',
    ])->assertSessionHasErrors('to_wallet_id');
});

test('a transfer requires a destination wallet', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'net' => '100',
    ])->assertSessionHasErrors('to_wallet_id');
});

test('changing an expense to a transfer clears its VAT lines', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $from = Wallet::factory()->create();
    $to = Wallet::factory()->create();
    $rate = VatRate::factory()->create(['rate' => 24]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '100', 'vat_rate_id' => $rate->id]],
    ]);
    $transaction = Transaction::first();
    expect($transaction->vatLines)->toHaveCount(1);

    $this->actingAs($user)->patch("/transactions/{$transaction->id}", [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => '100',
    ])->assertRedirect();

    $transaction->refresh();
    expect($transaction->type)->toBe('transfer');
    expect($transaction->vat_amount)->toBe('0.00');
    expect($transaction->to_wallet_id)->toBe($to->id);
    expect($transaction->fresh()->vatLines)->toHaveCount(0);
});

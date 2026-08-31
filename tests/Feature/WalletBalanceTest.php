<?php

use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\WalletBalances;

test('a wallet balance is its starting balance plus transaction effects', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    $vatRate = VatRate::factory()->create(['rate' => 24]);

    // Income of 100 net + 24 VAT => +124.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '100', 'vat_rate_id' => $vatRate->id]],
    ]);

    // Expense of 50 net, no VAT => -50.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '50', 'vat_rate_id' => null]],
    ]);

    expect(WalletBalances::all()[$wallet->id])->toBe(1074.0);
});

test('a transfer moves balance between wallets', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $from = Wallet::factory()->create(['starting_balance' => 500]);
    $to = Wallet::factory()->create(['starting_balance' => 0]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => '200',
    ]);

    $balances = WalletBalances::all();
    expect($balances[$from->id])->toBe(300.0);
    expect($balances[$to->id])->toBe(200.0);
});

test('the wallets page loads with balances', function () {
    $user = User::factory()->withFinanceAccess()->create();
    Wallet::factory()->create();

    $this->actingAs($user)->get('/wallets')->assertOk();
});

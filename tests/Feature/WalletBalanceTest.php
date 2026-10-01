<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\WalletBalances;
use Illuminate\Support\Carbon;

// Pinned "today", so "dated up to today" means the same thing on every run.
beforeEach(function () {
    Carbon::setTestNow('2026-08-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a wallet balance is its starting balance plus transaction effects', function () {
    $user = User::factory()->create();
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

    Transaction::query()->update(['is_reconciled' => true]);

    expect(WalletBalances::all()[$wallet->id])->toBe(1074.0);
});

test('a transfer moves balance between wallets', function () {
    $user = User::factory()->create();
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

    Transaction::query()->update(['is_reconciled' => true]);

    $balances = WalletBalances::all();
    expect($balances[$from->id])->toBe(300.0);
    expect($balances[$to->id])->toBe(200.0);
});

test('the wallet balance ignores transactions that are not reconciled', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 100, 'date' => '2026-08-05', 'is_reconciled' => true]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 300, 'date' => '2026-08-05', 'is_reconciled' => false]);
    Transaction::factory()->for($wallet)->create(['type' => 'income', 'net' => 500, 'date' => '2026-08-05', 'is_reconciled' => false]);

    expect(WalletBalances::all()[$wallet->id])->toBe(900.0);
});

test('the wallet balance ignores reconciled transactions dated after today', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 100, 'date' => '2026-08-15', 'is_reconciled' => true]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 300, 'date' => '2026-08-16', 'is_reconciled' => true]);

    expect(WalletBalances::all()[$wallet->id])->toBe(900.0);
});

test('an unreconciled transfer moves neither wallet', function () {
    $from = Wallet::factory()->create(['starting_balance' => 500]);
    $to = Wallet::factory()->create(['starting_balance' => 0]);
    Transaction::factory()->for($from)->create([
        'type' => 'transfer', 'net' => 200, 'to_wallet_id' => $to->id, 'date' => '2026-08-01', 'is_reconciled' => false,
    ]);

    $balances = WalletBalances::all();
    expect($balances[$from->id])->toBe(500.0);
    expect($balances[$to->id])->toBe(0.0);
});

test('the running balance counts every row, whatever its date or reconciled state', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 100, 'date' => '2026-08-01', 'is_reconciled' => true]);
    Transaction::factory()->for($wallet)->create(['type' => 'expense', 'net' => 200, 'date' => '2026-08-02', 'is_reconciled' => false]);
    Transaction::factory()->for($wallet)->create(['type' => 'income', 'net' => 50, 'date' => '2026-12-01', 'is_reconciled' => false]);

    $rows = WalletBalances::runningFor($wallet->id, 1000);

    expect($rows->pluck('balance')->all())->toBe([900.0, 700.0, 750.0]);
});

test('the wallets page loads with balances', function () {
    $user = User::factory()->create();
    Wallet::factory()->create();

    $this->actingAs($user)->get('/wallets')->assertOk();
});

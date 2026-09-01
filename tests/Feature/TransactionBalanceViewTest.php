<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('balance view shows a running balance seeded from the starting balance', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create(['starting_balance' => 100]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'net' => 50,
        'vat_amount' => 0,
        'withheld_amount' => 0,
        'date' => '2026-01-01',
    ]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'type' => 'expense',
        'net' => 30,
        'vat_amount' => 0,
        'withheld_amount' => 0,
        'date' => '2026-01-02',
    ]);

    $this->actingAs($user)
        ->get("/transactions?balance={$wallet->id}&all=1")
        ->assertInertia(fn (Assert $page) => $page
            ->where('balance.wallet_id', $wallet->id)
            ->has('transactions', 2)
            ->where('transactions.0.balance', fn ($v) => (float) $v === 150.0)
            ->where('transactions.1.balance', fn ($v) => (float) $v === 120.0));
});

test('a transfer moves the balance on both wallets in balance view', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $from = Wallet::factory()->create(['starting_balance' => 200]);
    $to = Wallet::factory()->create(['starting_balance' => 0]);
    Transaction::factory()->create([
        'type' => 'transfer',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => 80,
        'vat_amount' => 0,
        'withheld_amount' => 0,
        'date' => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->get("/transactions?balance={$from->id}&all=1")
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.balance', fn ($v) => (float) $v === 120.0));

    $this->actingAs($user)
        ->get("/transactions?balance={$to->id}&all=1")
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.balance', fn ($v) => (float) $v === 80.0));
});

test('filtering within balance view keeps cumulative balances', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create(['starting_balance' => 100]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'net' => 50,
        'vat_amount' => 0,
        'withheld_amount' => 0,
        'date' => '2026-01-01',
    ]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'type' => 'expense',
        'net' => 30,
        'vat_amount' => 0,
        'withheld_amount' => 0,
        'date' => '2026-01-02',
    ]);

    // Only the expense row shows, but its balance is still the cumulative 120.
    $this->actingAs($user)
        ->get("/transactions?balance={$wallet->id}&type=expense&all=1")
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.type', 'expense')
            ->where('transactions.0.balance', fn ($v) => (float) $v === 120.0));
});

test('the normal list carries no balance context', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)
        ->get('/transactions?all=1')
        ->assertInertia(fn (Assert $page) => $page->where('balance', null));
});

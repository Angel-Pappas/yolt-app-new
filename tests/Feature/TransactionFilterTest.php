<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('the transactions list can be filtered by type', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'type' => 'income']);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'type' => 'expense']);

    $this->actingAs($user)
        ->get('/transactions?type=income')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('filtering by wallet matches both sides of a transfer', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $a = Wallet::factory()->create();
    $b = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'transfer',
        'wallet_id' => $a->id,
        'to_wallet_id' => $b->id,
    ]);
    Transaction::factory()->create(['wallet_id' => $a->id, 'type' => 'expense']);

    $this->actingAs($user)
        ->get("/transactions?wallet={$b->id}")
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('searching matches the description', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'description' => 'Unique fuel purchase',
    ]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'description' => 'Something else',
    ]);

    $this->actingAs($user)
        ->get('/transactions?q=fuel')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('filtering by a date range', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-01-15']);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-08-15']);

    $this->actingAs($user)
        ->get('/transactions?from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

test('the description endpoint updates just the description', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'wallet_id' => Wallet::factory(),
        'description' => 'Old note',
    ]);

    $this->actingAs($user)
        ->patch("/transactions/{$transaction->id}/description", [
            'description' => 'New note',
        ])
        ->assertRedirect();

    expect($transaction->refresh()->description)->toBe('New note');
});

test('the description endpoint stores a cleared description as empty', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'wallet_id' => Wallet::factory(),
        'description' => 'Old note',
    ]);

    $this->actingAs($user)
        ->patch("/transactions/{$transaction->id}/description", [])
        ->assertRedirect();

    expect($transaction->refresh()->description)->toBe('');
});

test('a guest cannot update a description', function () {
    $transaction = Transaction::factory()->create([
        'wallet_id' => Wallet::factory(),
    ]);

    $this->patch("/transactions/{$transaction->id}/description", [
        'description' => 'x',
    ])->assertRedirect(route('login'));
});

test('?edit opens a transaction that is outside the current date filter', function () {
    $user = User::factory()->create();
    // Dated well before this month's default range.
    $transaction = Transaction::factory()->create([
        'wallet_id' => Wallet::factory(),
        'date' => '2024-01-15',
        'invoice_date' => '2024-01-15',
    ]);

    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();

    $this->actingAs($user)
        ->get("/transactions?from={$from}&to={$to}&edit={$transaction->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('editing.id', $transaction->id));
});

test('the finance lookups are shared on authenticated pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/transactions?all=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('financeLookups.wallets')
            ->has('financeLookups.entities')
            ->has('financeLookups.categories')
            ->has('financeLookups.vatRates')
            ->has('financeLookups.withheldRates'));
});

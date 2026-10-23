<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

/** A minimal expense payload for POST/PATCH /transactions. */
function oneOffPayload(Wallet $wallet, array $extra = []): array
{
    return array_merge([
        'type' => 'expense',
        'date' => '2026-10-01',
        'invoice_date' => '2026-10-01',
        'description' => 'New laptop',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '1200']],
    ], $extra);
}

test('a transaction added from the One-offs page is tagged a one-off', function () {
    $wallet = Wallet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/transactions', oneOffPayload($wallet, ['is_one_off' => true]))
        ->assertRedirect();

    expect(Transaction::sole()->is_one_off)->toBeTrue();
});

test('an ordinary transaction is not a one-off', function () {
    $wallet = Wallet::factory()->create();

    $this->actingAs(User::factory()->create())->post('/transactions', oneOffPayload($wallet));

    expect(Transaction::sole()->is_one_off)->toBeFalse();
});

test('editing a one-off keeps its tag', function () {
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/transactions', oneOffPayload($wallet, ['is_one_off' => true]));
    $transaction = Transaction::sole();

    // The shared edit form never sends the tag.
    $this->actingAs($user)
        ->patch("/transactions/{$transaction->id}", oneOffPayload($wallet, ['description' => 'Laptop']))
        ->assertRedirect();

    expect($transaction->refresh()->is_one_off)->toBeTrue();
    expect($transaction->description)->toBe('Laptop');
});

test('reconciling a one-off keeps its tag', function () {
    $transaction = Transaction::factory()->create(['is_one_off' => true, 'type' => 'expense']);

    $transaction->update(['is_reconciled' => true]);

    expect($transaction->refresh()->is_one_off)->toBeTrue();
});

test('the One-offs page lists only one-offs, open and reconciled', function () {
    $open = Transaction::factory()->create(['is_one_off' => true, 'is_reconciled' => false]);
    Transaction::factory()->create(['is_one_off' => true, 'is_reconciled' => true]);
    Transaction::factory()->create(['is_one_off' => false]);

    $this->actingAs(User::factory()->create())
        ->get('/one-offs')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('one-offs/index')
                ->has('transactions', 2)
                ->where('transactions.0.id', $open->id)
                ->where('transactions.1.is_reconciled', true),
        );
});

test('a deleted one-off is not listed', function () {
    Transaction::factory()->create(['is_one_off' => true])->delete();

    $this->actingAs(User::factory()->create())
        ->get('/one-offs')
        ->assertInertia(fn ($page) => $page->has('transactions', 0));
});

test('a guest cannot see the One-offs page', function () {
    $this->get('/one-offs')->assertRedirect(route('login'));
});

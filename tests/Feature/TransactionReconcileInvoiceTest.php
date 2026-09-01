<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('reconcile marks reconciled and can correct the fields', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $other = Wallet::factory()->create();
    $t = Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'type' => 'expense', 'net' => 100,
        'vat_amount' => 0, 'is_reconciled' => false, 'date' => '2026-05-01',
    ]);

    $this->actingAs($user)->post("/transactions/{$t->id}/reconcile", [
        'date' => '2026-05-10',
        'net' => '120',
        'wallet_id' => $other->id,
        'is_reconciled' => true,
    ])->assertRedirect();

    $t->refresh();
    expect($t->is_reconciled)->toBeTrue();
    expect($t->net)->toBe('120.00');
    expect($t->wallet_id)->toBe($other->id);
    expect($t->date->toDateString())->toBe('2026-05-10');
});

test('reconcile rescales VAT proportionally when the amount changes', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);
    // A single-line 100 @ 24% expense.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense', 'date' => '2026-05-01', 'invoice_date' => '2026-05-01',
        'wallet_id' => $wallet->id, 'amount_mode' => 'net',
        'lines' => [['amount' => '100', 'vat_rate_id' => $vatRate->id]],
    ])->assertRedirect();
    $t = Transaction::first();

    $this->actingAs($user)->post("/transactions/{$t->id}/reconcile", [
        'date' => '2026-05-01', 'net' => '200', 'wallet_id' => $wallet->id,
    ])->assertRedirect();

    $t->refresh();
    expect($t->net)->toBe('200.00');
    expect($t->vat_amount)->toBe('48.00'); // 200 * 24%
});

test('reconcile can unmark a transaction', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'type' => 'expense', 'net' => 50,
        'vat_amount' => 0, 'is_reconciled' => true,
    ]);

    $this->actingAs($user)->post("/transactions/{$t->id}/reconcile", [
        'date' => '2026-05-01', 'net' => '50', 'wallet_id' => $wallet->id,
        'is_reconciled' => false,
    ])->assertRedirect();

    expect($t->refresh()->is_reconciled)->toBeFalse();
});

test('an invoice month 1-12 files the transaction under that month', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)->post("/transactions/{$t->id}/invoice", ['month' => 6])
        ->assertRedirect();

    $t->refresh();
    expect($t->invoice_month)->toBe(6);
    expect($t->invoice_not_required)->toBeFalse();
});

test('invoice month 13 marks it as not required', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'invoice_month' => 4,
    ]);

    $this->actingAs($user)->post("/transactions/{$t->id}/invoice", ['month' => 13])
        ->assertRedirect();

    $t->refresh();
    expect($t->invoice_month)->toBeNull();
    expect($t->invoice_not_required)->toBeTrue();
});

test('a blank invoice month clears both flags', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'invoice_not_required' => true,
    ]);

    $this->actingAs($user)->post("/transactions/{$t->id}/invoice", ['month' => null])
        ->assertRedirect();

    $t->refresh();
    expect($t->invoice_month)->toBeNull();
    expect($t->invoice_not_required)->toBeFalse();
});

test('an invoice month above 13 is rejected', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)->post("/transactions/{$t->id}/invoice", ['month' => 14])
        ->assertSessionHasErrors('month');
});

test('the unreconciled quick filter narrows the list', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'is_reconciled' => true]);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'is_reconciled' => false]);

    $this->actingAs($user)
        ->get('/transactions?unreconciled=1&all=1')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('the missing-invoice quick filter excludes filed and not-needed rows', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'invoice_month' => 3]);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'invoice_not_required' => true]);
    Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'invoice_month' => null, 'invoice_not_required' => false,
    ]);

    $this->actingAs($user)
        ->get('/transactions?no_invoice=1&all=1')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('a non-finance user cannot reconcile', function () {
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs(User::factory()->create())
        ->post("/transactions/{$t->id}/reconcile")
        ->assertForbidden();
});

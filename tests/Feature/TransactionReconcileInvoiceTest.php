<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('reconcile toggles the reconciled flag', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create([
        'wallet_id' => $wallet->id, 'is_reconciled' => false,
    ]);

    $this->actingAs($user)->post("/transactions/{$t->id}/reconcile")->assertRedirect();
    expect($t->refresh()->is_reconciled)->toBeTrue();

    $this->actingAs($user)->post("/transactions/{$t->id}/reconcile")->assertRedirect();
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
        ->get('/transactions?unreconciled=1')
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
        ->get('/transactions?no_invoice=1')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('a non-finance user cannot reconcile', function () {
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs(User::factory()->create())
        ->post("/transactions/{$t->id}/reconcile")
        ->assertForbidden();
});

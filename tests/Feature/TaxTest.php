<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('a finance user can view the taxes index with current-month figures', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)
        ->get('/taxes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('taxes/index')
            ->has('vat')
            ->has('withheld'));
});

test('the VAT page renders the monthly ledger', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id,
        'vat_amount' => 100, 'invoice_date' => '2026-01-15',
    ]);

    $this->actingAs($user)
        ->get('/taxes/vat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('taxes/vat')
            ->has('rows'));
});

test('the withholding page renders the monthly ledger', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)
        ->get('/taxes/withheld')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('taxes/withheld')
            ->has('rows'));
});

test('a non-finance user cannot view taxes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/taxes')->assertForbidden();
    $this->actingAs($user)->get('/taxes/vat')->assertForbidden();
    $this->actingAs($user)->get('/taxes/withheld')->assertForbidden();
});

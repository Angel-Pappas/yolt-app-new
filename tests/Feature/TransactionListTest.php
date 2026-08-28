<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

test('the transactions list shows transactions to a finance user', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->count(3)->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)
        ->get('/transactions')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('transactions/index')
                ->has('transactions', 3)
        );
});

test('a non-finance user cannot see the transactions list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/transactions')
        ->assertForbidden();
});

<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('a bare visit defaults to the current month', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)
        ->get('/transactions')
        ->assertRedirect('/transactions?from='.Carbon::now()->startOfMonth()->toDateString().'&to='.Carbon::now()->endOfMonth()->toDateString());
});

test('all=1 shows every month without redirecting', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->get('/transactions?all=1')->assertOk();
});

test('the transactions list shows transactions to a finance user', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->count(3)->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)
        ->get('/transactions?all=1')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('transactions/index')
                ->has('transactions', 3)
        );
});

test('a non-finance user cannot see the transactions list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/transactions?all=1')
        ->assertForbidden();
});

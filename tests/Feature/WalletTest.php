<?php

use App\Models\User;
use App\Models\Wallet;

test('a finance user can view the wallets page', function () {
    $user = User::factory()->withFinanceAccess()->create();
    Wallet::factory()->count(2)->create();

    $this->actingAs($user)->get('/wallets')->assertOk();
});

test('a non-finance user cannot view wallets', function () {
    $this->actingAs(User::factory()->create())
        ->get('/wallets')
        ->assertForbidden();
});

test('a finance user can create a wallet', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/wallets', [
        'name' => 'Alpha Bank',
        'starting_balance' => '1500.50',
    ])->assertRedirect();

    $wallet = Wallet::where('name', 'Alpha Bank')->first();
    expect($wallet)->not->toBeNull();
    expect($wallet->starting_balance)->toBe('1500.50');
    expect($wallet->user_id)->toBe($user->id);
});

test('creating a wallet requires a name', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/wallets', [
        'name' => '',
        'starting_balance' => '0',
    ])->assertSessionHasErrors('name');
});

test('a finance user can update a wallet', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/wallets/{$wallet->id}", [
        'name' => 'New',
        'starting_balance' => '99.99',
    ])->assertRedirect();

    expect($wallet->refresh()->name)->toBe('New');
});

test('a finance user can soft-delete a wallet', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->delete("/wallets/{$wallet->id}")->assertRedirect();

    expect(Wallet::find($wallet->id))->toBeNull();
    expect(Wallet::withTrashed()->find($wallet->id))->not->toBeNull();
});

test('a non-finance user cannot create a wallet', function () {
    $this->actingAs(User::factory()->create())->post('/wallets', [
        'name' => 'X',
        'starting_balance' => '0',
    ])->assertForbidden();
});

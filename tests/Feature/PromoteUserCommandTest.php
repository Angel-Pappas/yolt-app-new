<?php

use App\Models\User;

test('the promote command grants a user full super-admin access', function () {
    $user = User::factory()->create();

    $this->artisan('user:promote', ['email' => $user->email])
        ->assertSuccessful();

    $user->refresh();

    expect($user->is_admin)->toBeTrue();
    expect($user->can_access_finance)->toBeTrue();
    expect($user->can_access_crm)->toBeTrue();
    expect($user->is_active)->toBeTrue();
});

test('the promote command fails for an unknown email', function () {
    $this->artisan('user:promote', ['email' => 'nobody@example.com'])
        ->assertFailed();
});

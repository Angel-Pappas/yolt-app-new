<?php

use App\Models\User;

test('a new user has no area access and is active by default', function () {
    $user = User::factory()->create();

    expect($user->is_admin)->toBeFalse();
    expect($user->can_access_finance)->toBeFalse();
    expect($user->is_active)->toBeTrue();
});

test('the access-finance gate follows the finance flag', function () {
    expect(User::factory()->withFinanceAccess()->create()->can('access-finance'))->toBeTrue();
    expect(User::factory()->create()->can('access-finance'))->toBeFalse();
});

test('the admin gate follows the admin flag', function () {
    expect(User::factory()->admin()->create()->can('admin'))->toBeTrue();
    expect(User::factory()->create()->can('admin'))->toBeFalse();
});

test('a deactivated user is denied every gate even with all flags set', function () {
    $user = User::factory()
        ->admin()
        ->withFinanceAccess()
        ->inactive()
        ->create();

    expect($user->can('admin'))->toBeFalse();
    expect($user->can('access-finance'))->toBeFalse();
});

test('a deactivated user is logged out when visiting an authenticated page', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)
        ->get('/transactions')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active user is not affected by the deactivation guard', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)
        ->get('/transactions?all=1')
        ->assertOk();
});

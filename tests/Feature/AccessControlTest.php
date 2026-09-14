<?php

use App\Models\User;

test('a new user is active and not an admin by default', function () {
    $user = User::factory()->create();

    expect($user->is_admin)->toBeFalse();
    expect($user->is_active)->toBeTrue();
});

test('the admin gate follows the admin flag', function () {
    expect(User::factory()->admin()->create()->can('admin'))->toBeTrue();
    expect(User::factory()->create()->can('admin'))->toBeFalse();
});

test('a deactivated admin is denied the admin gate', function () {
    $user = User::factory()->admin()->inactive()->create();

    expect($user->can('admin'))->toBeFalse();
});

test('a deactivated user is logged out when visiting an authenticated page', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)
        ->get('/transactions')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active user is not affected by the deactivation guard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/transactions?all=1')
        ->assertOk();
});

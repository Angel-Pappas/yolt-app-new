<?php

use App\Models\User;

test('an admin can view the users list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/settings/users')->assertOk();
});

test('a non-admin cannot view the users list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/users')
        ->assertForbidden();
});

test('an admin can grant a user finance access', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch("/settings/users/{$target->id}", [
        'is_admin' => false,
        'can_access_finance' => true,
        'can_access_crm' => false,
        'is_active' => true,
    ])->assertRedirect();

    $target->refresh();
    expect($target->can_access_finance)->toBeTrue();
    expect($target->can_access_crm)->toBeFalse();
    expect($target->is_admin)->toBeFalse();
});

test('an admin cannot remove their own admin or deactivate themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch("/settings/users/{$admin->id}", [
        'is_admin' => false,
        'can_access_finance' => false,
        'can_access_crm' => false,
        'is_active' => false,
    ])->assertRedirect();

    $admin->refresh();
    expect($admin->is_admin)->toBeTrue();
    expect($admin->is_active)->toBeTrue();
});

test('a non-admin cannot update another user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->patch("/settings/users/{$target->id}", [
        'is_admin' => true,
        'can_access_finance' => true,
        'can_access_crm' => true,
        'is_active' => true,
    ])->assertForbidden();

    expect($target->refresh()->can_access_finance)->toBeFalse();
});

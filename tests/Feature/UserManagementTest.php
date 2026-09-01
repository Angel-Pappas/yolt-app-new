<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

test('an admin can invite a user and gets a set-password link', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/settings/users', [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'is_admin' => false,
        'can_access_finance' => true,
        'can_access_crm' => false,
    ]);

    $response->assertRedirect();
    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->can_access_finance)->toBeTrue();
    expect($user->can_access_crm)->toBeFalse();
    expect($user->is_active)->toBeTrue();

    // The invited user can set their password via the flashed link's token.
    $token = Password::broker()->createToken($user);
    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'new@example.com',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasNoErrors();
});

test('a duplicate invite email is rejected', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)->post('/settings/users', [
        'name' => 'Dup',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');
});

test('a non-admin cannot invite a user', function () {
    $this->actingAs(User::factory()->create())->post('/settings/users', [
        'name' => 'X',
        'email' => 'x@example.com',
    ])->assertForbidden();
});

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

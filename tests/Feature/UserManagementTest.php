<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

test('an admin can invite a user and gets a set-password link', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/configuration/users', [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'is_admin' => false,
    ]);

    $response->assertRedirect();
    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->is_admin)->toBeFalse();
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

    $this->actingAs($admin)->post('/configuration/users', [
        'name' => 'Dup',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');
});

test('a non-admin cannot invite a user', function () {
    $this->actingAs(User::factory()->create())->post('/configuration/users', [
        'name' => 'X',
        'email' => 'x@example.com',
    ])->assertForbidden();
});

test('an admin can view the users list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/configuration/users')->assertOk();
});

test('a non-admin cannot view the users list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/configuration/users')
        ->assertForbidden();
});

test('an admin can make another user an admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch("/configuration/users/{$target->id}", [
        'is_admin' => true,
        'is_active' => true,
    ])->assertRedirect();

    expect($target->refresh()->is_admin)->toBeTrue();
});

test('an admin can deactivate another user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch("/configuration/users/{$target->id}", [
        'is_admin' => false,
        'is_active' => false,
    ])->assertRedirect();

    expect($target->refresh()->is_active)->toBeFalse();
});

test('an admin cannot remove their own admin or deactivate themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch("/configuration/users/{$admin->id}", [
        'is_admin' => false,
        'is_active' => false,
    ])->assertRedirect();

    $admin->refresh();
    expect($admin->is_admin)->toBeTrue();
    expect($admin->is_active)->toBeTrue();
});

test('a non-admin cannot update another user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->patch("/configuration/users/{$target->id}", [
        'is_admin' => true,
        'is_active' => true,
    ])->assertForbidden();

    expect($target->refresh()->is_admin)->toBeFalse();
});

<?php

use App\Models\User;

test('any active user can open the app and the configuration lists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions?all=1')->assertOk();
    $this->actingAs($user)->get('/configuration')->assertOk();
    $this->actingAs($user)->get('/configuration/categories')->assertOk();
    $this->actingAs($user)->get('/configuration/vat-rates')->assertOk();
    $this->actingAs($user)->get('/configuration/withheld-tax-rates')->assertOk();
});

test('a guest is redirected to login', function () {
    $this->get('/transactions?all=1')->assertRedirect(route('login'));
    $this->get('/configuration')->assertRedirect(route('login'));
});

test('a deactivated user is locked out of the app', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->get('/transactions?all=1')->assertRedirect(route('login'));
});

test('only an admin can open the users list', function () {
    $this->actingAs(User::factory()->create())->get('/configuration/users')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/configuration/users')->assertOk();
});

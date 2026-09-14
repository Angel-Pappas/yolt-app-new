<?php

use App\Models\User;

test('a finance user can open the finance area', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->get('/transactions?all=1')->assertOk();
});

test('a user with no access is forbidden from the finance area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions?all=1')->assertForbidden();
});

test('a guest is redirected to login from the finance area', function () {
    $this->get('/transactions?all=1')->assertRedirect(route('login'));
});

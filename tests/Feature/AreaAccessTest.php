<?php

use App\Models\User;

test('a finance user can open the finance area but not the business area', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->get('/transactions')->assertOk();
    $this->actingAs($user)->get('/leads')->assertForbidden();
});

test('a crm user can open the business area but not the finance area', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->get('/leads')->assertOk();
    $this->actingAs($user)->get('/transactions')->assertForbidden();
});

test('a user with no access is forbidden from both areas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions')->assertForbidden();
    $this->actingAs($user)->get('/leads')->assertForbidden();
});

test('a user with both grants can open both areas', function () {
    $user = User::factory()->withFinanceAccess()->withCrmAccess()->create();

    $this->actingAs($user)->get('/transactions')->assertOk();
    $this->actingAs($user)->get('/leads')->assertOk();
});

test('a guest is redirected to login from the area pages', function () {
    $this->get('/transactions')->assertRedirect(route('login'));
    $this->get('/leads')->assertRedirect(route('login'));
});

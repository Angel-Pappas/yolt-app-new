<?php

use App\Models\User;
use App\Models\WithheldTaxRate;

test('a finance user can view withheld tax rates', function () {
    $user = User::factory()->create();
    WithheldTaxRate::factory()->count(2)->create();

    $this->actingAs($user)->get('/configuration/withheld-tax-rates')->assertOk();
});

test('a guest cannot view withheld tax rates', function () {
    $this->get('/configuration/withheld-tax-rates')->assertRedirect(route('login'));
});

test('a finance user can create a withheld tax rate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/withheld-tax-rates', [
        'name' => 'Contractor',
        'rate' => '20',
    ])->assertRedirect();

    $rate = WithheldTaxRate::where('name', 'Contractor')->first();
    expect($rate)->not->toBeNull();
    expect($rate->rate)->toBe('20.00');
    expect($rate->user_id)->toBe($user->id);
});

test('a withheld tax rate must be numeric', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/withheld-tax-rates', [
        'name' => 'X',
        'rate' => 'abc',
    ])->assertSessionHasErrors('rate');
});

test('a finance user can soft-delete a withheld tax rate', function () {
    $user = User::factory()->create();
    $rate = WithheldTaxRate::factory()->create();

    $this->actingAs($user)
        ->delete("/configuration/withheld-tax-rates/{$rate->id}")
        ->assertRedirect();

    expect(WithheldTaxRate::find($rate->id))->toBeNull();
    expect(WithheldTaxRate::withTrashed()->find($rate->id))->not->toBeNull();
});

test('a guest cannot create a withheld tax rate', function () {
    $this->post('/configuration/withheld-tax-rates', [
        'name' => 'X',
        'rate' => '10',
    ])->assertRedirect(route('login'));
});

<?php

use App\Models\User;
use App\Models\VatRate;

test('a finance user can view VAT rates', function () {
    $user = User::factory()->create();
    VatRate::factory()->count(2)->create();

    $this->actingAs($user)->get('/configuration/vat-rates')->assertOk();
});

test('a guest cannot view VAT rates', function () {
    $this->get('/configuration/vat-rates')->assertRedirect(route('login'));
});

test('a finance user can create a VAT rate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/vat-rates', [
        'name' => 'Standard',
        'rate' => '24',
    ])->assertRedirect();

    $rate = VatRate::where('name', 'Standard')->first();
    expect($rate)->not->toBeNull();
    expect($rate->rate)->toBe('24.00');
    expect($rate->user_id)->toBe($user->id);
});

test('a VAT rate must be numeric', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/vat-rates', [
        'name' => 'X',
        'rate' => 'abc',
    ])->assertSessionHasErrors('rate');
});

test('a finance user can update a VAT rate', function () {
    $user = User::factory()->create();
    $rate = VatRate::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/configuration/vat-rates/{$rate->id}", [
        'name' => 'New',
        'rate' => '13',
    ])->assertRedirect();

    expect($rate->refresh()->name)->toBe('New');
});

test('a finance user can soft-delete a VAT rate', function () {
    $user = User::factory()->create();
    $rate = VatRate::factory()->create();

    $this->actingAs($user)->delete("/configuration/vat-rates/{$rate->id}")->assertRedirect();

    expect(VatRate::find($rate->id))->toBeNull();
    expect(VatRate::withTrashed()->find($rate->id))->not->toBeNull();
});

test('a guest cannot create a VAT rate', function () {
    $this->post('/configuration/vat-rates', [
        'name' => 'X',
        'rate' => '10',
    ])->assertRedirect(route('login'));
});

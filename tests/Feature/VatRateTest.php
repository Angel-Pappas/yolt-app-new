<?php

use App\Models\User;
use App\Models\VatRate;

test('a finance user can view VAT rates', function () {
    $user = User::factory()->withFinanceAccess()->create();
    VatRate::factory()->count(2)->create();

    $this->actingAs($user)->get('/vat-rates')->assertOk();
});

test('a non-finance user cannot view VAT rates', function () {
    $this->actingAs(User::factory()->create())
        ->get('/vat-rates')
        ->assertForbidden();
});

test('a finance user can create a VAT rate', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/vat-rates', [
        'name' => 'Standard',
        'rate' => '24',
    ])->assertRedirect();

    $rate = VatRate::where('name', 'Standard')->first();
    expect($rate)->not->toBeNull();
    expect($rate->rate)->toBe('24.00');
    expect($rate->user_id)->toBe($user->id);
});

test('a VAT rate must be numeric', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/vat-rates', [
        'name' => 'X',
        'rate' => 'abc',
    ])->assertSessionHasErrors('rate');
});

test('a finance user can update a VAT rate', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $rate = VatRate::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/vat-rates/{$rate->id}", [
        'name' => 'New',
        'rate' => '13',
    ])->assertRedirect();

    expect($rate->refresh()->name)->toBe('New');
});

test('a finance user can soft-delete a VAT rate', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $rate = VatRate::factory()->create();

    $this->actingAs($user)->delete("/vat-rates/{$rate->id}")->assertRedirect();

    expect(VatRate::find($rate->id))->toBeNull();
    expect(VatRate::withTrashed()->find($rate->id))->not->toBeNull();
});

test('a non-finance user cannot create a VAT rate', function () {
    $this->actingAs(User::factory()->create())->post('/vat-rates', [
        'name' => 'X',
        'rate' => '10',
    ])->assertForbidden();
});

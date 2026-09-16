<?php

use App\Models\IncomeTaxYear;
use App\Models\User;

test('an active user can view the income tax page', function () {
    $user = User::factory()->create();
    IncomeTaxYear::factory()->create();

    $this->actingAs($user)->get('/taxes/income')->assertOk();
});

test('a guest cannot view income tax', function () {
    $this->get('/taxes/income')->assertRedirect(route('login'));
});

test('an active user can create an income tax year, months normalized to the 1st', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/taxes/income', [
        'year' => '2025',
        'revenue_before_tax' => '100000',
        'total_tax' => '22000',
        'first_installment_month' => '2026-06-15',
        'last_installment_month' => '2027-01-20',
        'monthly_installment_amount' => '2750',
    ])->assertRedirect();

    $record = IncomeTaxYear::where('year', 2025)->first();
    expect($record)->not->toBeNull();
    expect($record->first_installment_month->format('Y-m-d'))->toBe('2026-06-01');
    expect($record->last_installment_month->format('Y-m-d'))->toBe('2027-01-01');
    expect($record->monthly_installment_amount)->toBe('2750.00');
    expect($record->user_id)->toBe($user->id);
});

test('the same year cannot be entered twice', function () {
    $user = User::factory()->create();
    IncomeTaxYear::factory()->create(['year' => 2025]);

    $this->actingAs($user)->post('/taxes/income', [
        'year' => '2025',
        'revenue_before_tax' => '1',
        'total_tax' => '1',
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-07-01',
        'monthly_installment_amount' => '1',
    ])->assertSessionHasErrors('year');
});

test('the last installment month must not precede the first', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/taxes/income', [
        'year' => '2025',
        'revenue_before_tax' => '1',
        'total_tax' => '1',
        'first_installment_month' => '2026-07-01',
        'last_installment_month' => '2026-06-01',
        'monthly_installment_amount' => '1',
    ])->assertSessionHasErrors('last_installment_month');
});

test('an active user can soft-delete an income tax year', function () {
    $user = User::factory()->create();
    $record = IncomeTaxYear::factory()->create();

    $this->actingAs($user)
        ->delete("/taxes/income/{$record->id}")
        ->assertRedirect();

    expect(IncomeTaxYear::find($record->id))->toBeNull();
    expect(IncomeTaxYear::withTrashed()->find($record->id))->not->toBeNull();
});

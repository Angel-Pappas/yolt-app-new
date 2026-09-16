<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-09-16');
});

test('the taxes index exposes the payment-schedule obligations', function () {
    $user = User::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 100, 'invoice_date' => '2026-08-15',
    ]);

    $this->actingAs($user)->get('/taxes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('taxes/index')
            ->has('current_month')
            ->has('obligations', 1)
            ->where('obligations.0.tax', 'vat'));
});

test('the VAT page lists only its contributing transactions', function () {
    $user = User::factory()->create();
    Transaction::factory()->create([
        'type' => 'income', 'vat_amount' => 100, 'invoice_date' => '2026-08-15',
    ]);
    // A zero-VAT expense does not contribute.
    Transaction::factory()->create([
        'type' => 'expense', 'vat_amount' => 0, 'invoice_date' => '2026-08-15',
    ]);

    $this->actingAs($user)->get('/taxes/vat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('taxes/vat')
            ->has('transactions', 1));
});

test('the withholding page lists only withholding transactions', function () {
    $user = User::factory()->create();
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 50, 'invoice_date' => '2026-08-15',
    ]);
    Transaction::factory()->create([
        'type' => 'expense', 'withheld_amount' => 0, 'invoice_date' => '2026-08-15',
    ]);

    $this->actingAs($user)->get('/taxes/withheld')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('the FMY and EFKA pages list their contributing transactions', function () {
    $user = User::factory()->create();
    Transaction::factory()->create([
        'type' => 'expense',
        'fmy_amount' => 80,
        'efka_employee_amount' => 150,
        'efka_employer_amount' => 250,
        'invoice_date' => '2026-08-15',
    ]);

    $this->actingAs($user)->get('/taxes/fmy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));

    $this->actingAs($user)->get('/taxes/efka')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

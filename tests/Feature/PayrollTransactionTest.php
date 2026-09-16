<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\WalletBalances;

/** Create the "Payroll" category that triggers the payroll shape. */
function payrollCategory(): Category
{
    return Category::factory()->create(['name' => 'Payroll', 'type' => 'expense']);
}

test('a payroll transaction stores net plus manual FMY and EFKA amounts', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $payroll = payrollCategory();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'description' => 'August salary',
        'wallet_id' => $wallet->id,
        'category_id' => $payroll->id,
        'net' => '1000',
        'fmy_amount' => '80',
        'efka_employee_amount' => '150',
        'efka_employer_amount' => '250',
    ])->assertRedirect();

    $t = Transaction::first();
    expect($t->net)->toBe('1000.00');
    expect($t->vat_amount)->toBe('0.00');
    expect($t->withheld_amount)->toBe('0.00');
    expect($t->fmy_amount)->toBe('80.00');
    expect($t->efka_employee_amount)->toBe('150.00');
    expect($t->efka_employer_amount)->toBe('250.00');
    expect($t->vatLines)->toHaveCount(0);
    expect($t->withheldLines)->toHaveCount(0);
});

test('a payroll transaction moves the wallet by To Pay (net − FMY − EFKA employee)', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['starting_balance' => 0]);
    $payroll = payrollCategory();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'category_id' => $payroll->id,
        'net' => '1000',
        'fmy_amount' => '80',
        'efka_employee_amount' => '150',
        'efka_employer_amount' => '250',
    ])->assertRedirect();

    // To Pay = 1000 − 80 − 150 = 770 leaves the wallet; employer EFKA (250) never does.
    expect(WalletBalances::all()[$wallet->id])->toBe(-770.0);
});

test('a normal expense is unaffected by the payroll fields', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['starting_balance' => 0]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '100', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $t = Transaction::first();
    expect($t->fmy_amount)->toBeNull();
    expect($t->efka_employee_amount)->toBeNull();
    expect($t->efka_employer_amount)->toBeNull();
    expect(WalletBalances::all()[$wallet->id])->toBe(-100.0);
});

test('editing a payroll transaction to a normal expense clears the payroll amounts', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $payroll = payrollCategory();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'category_id' => $payroll->id,
        'net' => '1000',
        'fmy_amount' => '80',
        'efka_employee_amount' => '150',
        'efka_employer_amount' => '250',
    ])->assertRedirect();

    $t = Transaction::first();
    expect($t->fmy_amount)->toBe('80.00');

    $this->actingAs($user)->patch("/transactions/{$t->id}", [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '1000', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $t->refresh();
    expect($t->fmy_amount)->toBeNull();
    expect($t->efka_employee_amount)->toBeNull();
    expect($t->efka_employer_amount)->toBeNull();
});

<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;

test('an amount line can carry withholding computed server-side', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'description' => 'Contractor fee',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [[
            'amount' => '1000',
            'vat_rate_id' => $vatRate->id,
            'withheld_rate_id' => $withheldRate->id,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->net)->toBe('1000.00');
    expect($transaction->vat_amount)->toBe('240.00');
    expect($transaction->withheld_amount)->toBe('200.00');
    expect($transaction->withheldLines)->toHaveCount(1);
    // The withheld line's base is the line's own net (not re-typed).
    expect($transaction->withheldLines->first()->net)->toBe('1000.00');
    expect($transaction->withheldLines->first()->position)
        ->toBe($transaction->vatLines->first()->position);
    // Cash total = net + VAT − withheld.
    $total = (float) $transaction->net + (float) $transaction->vat_amount - (float) $transaction->withheld_amount;
    expect($total)->toBe(1040.0);
});

test('withholding is derived from the rate and the line net, not the client', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [[
            'amount' => '500',
            'vat_rate_id' => null,
            'withheld_rate_id' => $withheldRate->id,
        ]],
    ])->assertRedirect();

    expect(Transaction::first()->withheld_amount)->toBe('100.00');
});

test('withholding is optional', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '80', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

test('editing away withholding clears the withheld lines', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [[
            'amount' => '300',
            'vat_rate_id' => null,
            'withheld_rate_id' => $withheldRate->id,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('60.00');

    $this->actingAs($user)->patch("/transactions/{$transaction->id}", [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '300', 'vat_rate_id' => null]],
    ])->assertRedirect();

    $transaction->refresh();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

test('total mode reverses net out of the cash total with VAT and withholding', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    // Cash total 1040 → net 1000, VAT 240, withheld 200, reconstructing exactly.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'total',
        'lines' => [[
            'amount' => '1040',
            'vat_rate_id' => $vatRate->id,
            'withheld_rate_id' => $withheldRate->id,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->net)->toBe('1000.00');
    expect($transaction->vat_amount)->toBe('240.00');
    expect($transaction->withheld_amount)->toBe('200.00');
    $total = (float) $transaction->net + (float) $transaction->vat_amount - (float) $transaction->withheld_amount;
    expect($total)->toBe(1040.0);
});

test('reconciling a new amount rescales the withheld line too', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 24]);
    $withheldRate = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [[
            'amount' => '1000',
            'vat_rate_id' => $vatRate->id,
            'withheld_rate_id' => $withheldRate->id,
        ]],
    ])->assertRedirect();

    $transaction = Transaction::first();

    $this->actingAs($user)->post("/transactions/{$transaction->id}/reconcile", [
        'date' => '2026-08-01',
        'net' => '500',
        'wallet_id' => $wallet->id,
    ])->assertRedirect();

    $transaction->refresh();
    expect($transaction->net)->toBe('500.00');
    expect($transaction->vat_amount)->toBe('120.00');
    expect($transaction->withheld_amount)->toBe('100.00');
    expect($transaction->withheldLines->first()->net)->toBe('500.00');
});

test('a transfer records no withholding', function () {
    $user = User::factory()->create();
    $from = Wallet::factory()->create();
    $to = Wallet::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'net' => '250',
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction->withheld_amount)->toBe('0.00');
    expect($transaction->withheldLines)->toHaveCount(0);
});

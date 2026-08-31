<?php

use App\Models\Transaction;
use App\Models\TransactionVatLine;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;

test('a finance user can update a transaction and its VAT is recomputed', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $rate24 = VatRate::factory()->create(['rate' => 24]);
    $rate13 = VatRate::factory()->create(['rate' => 13]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '100', 'vat_rate_id' => $rate24->id]],
    ]);
    $transaction = Transaction::first();
    expect($transaction->vat_amount)->toBe('24.00');

    $this->actingAs($user)->patch("/transactions/{$transaction->id}", [
        'type' => 'expense',
        'date' => '2026-08-02',
        'invoice_date' => '2026-08-02',
        'description' => 'Updated',
        'wallet_id' => $wallet->id,
        'lines' => [['net' => '200', 'vat_rate_id' => $rate13->id]],
    ])->assertRedirect();

    $transaction->refresh();
    expect($transaction->net)->toBe('200.00');
    expect($transaction->vat_amount)->toBe('26.00');
    expect($transaction->description)->toBe('Updated');
    expect($transaction->vat_rate_id)->toBe($rate13->id);
    // Lines are rewritten wholesale on every save -> still exactly one line.
    expect(TransactionVatLine::where('transaction_id', $transaction->id)->count())->toBe(1);
});

test('a finance user can soft-delete a transaction', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $transaction = Transaction::factory()->create();

    $this->actingAs($user)
        ->delete("/transactions/{$transaction->id}")
        ->assertRedirect();

    expect(Transaction::find($transaction->id))->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});

test('a non-finance user cannot update or delete a transaction', function () {
    $transaction = Transaction::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->patch("/transactions/{$transaction->id}", [
        'type' => 'expense',
        'date' => '2026-08-01',
        'invoice_date' => '2026-08-01',
        'wallet_id' => $transaction->wallet_id,
        'lines' => [['net' => '1', 'vat_rate_id' => null]],
    ])->assertForbidden();

    $this->actingAs($user)
        ->delete("/transactions/{$transaction->id}")
        ->assertForbidden();
});

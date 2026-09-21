<?php

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\TaxSync;
use App\Support\VatLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-03-10');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A February income with VAT — payable the following month. */
function vatIncome(Wallet $wallet, float $vat = 24): Transaction
{
    return Transaction::factory()->create([
        'type' => 'income',
        'date' => '2026-02-15',
        'invoice_date' => '2026-02-15',
        'net' => 100,
        'vat_amount' => $vat,
        'wallet_id' => $wallet->id,
    ]);
}

test('a VAT obligation materialises as a State/Taxes expense', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet);

    TaxSync::run();

    $tax = Transaction::where('managed_key', 'tax:vat:2026-02')->first();
    expect($tax)->not->toBeNull();
    expect($tax->source)->toBe('tax');
    expect($tax->type)->toBe('expense');
    expect((float) $tax->net)->toBe(24.0);
    expect($tax->entity->type)->toBe('state');
    expect($tax->category->name)->toBe('Taxes');
    expect($tax->description)->toContain('VAT');
});

test('the tax row is recomputed when the underlying VAT changes', function () {
    $wallet = Wallet::factory()->create();
    $income = vatIncome($wallet, 24);
    TaxSync::run();

    $income->update(['vat_amount' => 50]);
    TaxSync::run();

    expect((float) Transaction::where('managed_key', 'tax:vat:2026-02')->value('net'))->toBe(50.0);
    // Still exactly one VAT tax row for the month.
    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->count())->toBe(1);
});

test('the tax row is removed when the obligation disappears', function () {
    $wallet = Wallet::factory()->create();
    $income = vatIncome($wallet);
    TaxSync::run();
    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->exists())->toBeTrue();

    $income->delete();
    TaxSync::run();

    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->exists())->toBeFalse();
});

test('a reconciled tax row is frozen against recompute', function () {
    $wallet = Wallet::factory()->create();
    $income = vatIncome($wallet, 24);
    TaxSync::run();

    // The user pays it: reconcile with the actual amount.
    Transaction::where('managed_key', 'tax:vat:2026-02')
        ->first()
        ->update(['is_reconciled' => true, 'net' => 30]);

    $income->update(['vat_amount' => 90]);
    TaxSync::run();

    expect((float) Transaction::where('managed_key', 'tax:vat:2026-02')->value('net'))->toBe(30.0);
});

test('a tax payment due before the managed floor is not generated', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet); // Feb 2026 VAT, due end of March 2026

    Setting::current()->update(['managed_from' => '2026-04-01']);
    TaxSync::run();

    // The March-due payment is before the April floor, so it isn't materialised.
    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->exists())->toBeFalse();
});

test('tax transactions use the configured tax wallet', function () {
    $fallback = Wallet::factory()->create(); // first by id — the default
    $chosen = Wallet::factory()->create();
    Setting::current()->update(['tax_wallet_id' => $chosen->id]);
    vatIncome($fallback);

    TaxSync::run();

    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->value('wallet_id'))->toBe($chosen->id);
});

test('changing the tax wallet moves unreconciled tax rows but not reconciled ones', function () {
    $user = User::factory()->create();
    $walletA = Wallet::factory()->create();
    $walletB = Wallet::factory()->create();
    Setting::current()->update(['tax_wallet_id' => $walletA->id]);
    vatIncome($walletA);
    TaxSync::run();

    $row = Transaction::where('managed_key', 'tax:vat:2026-02')->first();
    expect($row->wallet_id)->toBe($walletA->id);
    $row->update(['is_reconciled' => true]); // pay it → frozen

    $this->actingAs($user)
        ->patch('/taxes/wallet', ['tax_wallet_id' => $walletB->id])
        ->assertRedirect();

    // Reconciled row stays on wallet A (frozen).
    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->value('wallet_id'))->toBe($walletA->id);
});

test('generated tax rows do not feed back into the VAT ledger', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet, 24);

    TaxSync::run();

    $feb = collect(VatLedger::monthly())->firstWhere('month', '2026-02');
    expect((float) $feb['income_vat'])->toBe(24.0);
    // The generated tax expense carries vat 0, so it never adds input VAT.
    expect((float) $feb['expense_vat'])->toBe(0.0);
});

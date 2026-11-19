<?php

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
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

test('a VAT obligation is planned as a State/Taxes expense', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet);

    $tax = taxPayment('tax:vat:2026-02')->template;
    expect($tax->source)->toBe('tax');
    expect($tax->type)->toBe('expense');
    expect((float) $tax->net)->toBe(24.0);
    expect($tax->entity->type)->toBe('state');
    expect($tax->category->name)->toBe('Taxes');
    expect($tax->description)->toContain('VAT');
    // Nothing is stored: the payment is planned, not a transaction.
    expect(Transaction::where('managed_key', 'tax:vat:2026-02')->exists())->toBeFalse();
});

test('the planned payment follows the underlying VAT', function () {
    $wallet = Wallet::factory()->create();
    $income = vatIncome($wallet, 24);
    expect(taxPayments()['tax:vat:2026-02'])->toBe(24.0);

    $income->update(['vat_amount' => 50]);

    expect(taxPayments()['tax:vat:2026-02'])->toBe(50.0);
});

test('the planned payment goes when the obligation disappears', function () {
    $wallet = Wallet::factory()->create();
    $income = vatIncome($wallet);
    expect(taxPayments())->toHaveKey('tax:vat:2026-02');

    $income->delete();

    expect(taxPayments())->not->toHaveKey('tax:vat:2026-02');
});

test('a real payment carrying the key settles the planned one, whatever its exact amount', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet, 24);

    Transaction::factory()->create([
        'type' => 'expense', 'date' => '2026-03-31', 'invoice_date' => '2026-03-31',
        'net' => 23.50, 'vat_amount' => 0, 'wallet_id' => $wallet->id,
        'source' => 'tax', 'managed_key' => 'tax:vat:2026-02', 'is_reconciled' => true,
    ]);

    expect(taxPayments())->not->toHaveKey('tax:vat:2026-02');
});

test('a tax payment due before the plan floor is not planned', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet); // Feb 2026 VAT, due end of March 2026

    Setting::current()->update(['managed_from' => '2026-04-01']);

    expect(taxPayments())->not->toHaveKey('tax:vat:2026-02');
});

test('tax payments are planned in the configured tax wallet', function () {
    $fallback = Wallet::factory()->create(); // first by id — the default
    $chosen = Wallet::factory()->create();
    Setting::current()->update(['tax_wallet_id' => $chosen->id]);
    vatIncome($fallback);

    expect(taxPayment('tax:vat:2026-02')->template->wallet_id)->toBe($chosen->id);
});

test('changing the tax wallet moves the planned payments', function () {
    $user = User::factory()->create();
    $walletA = Wallet::factory()->create();
    $walletB = Wallet::factory()->create();
    Setting::current()->update(['tax_wallet_id' => $walletA->id]);
    vatIncome($walletA);
    expect(taxPayment('tax:vat:2026-02')->template->wallet_id)->toBe($walletA->id);

    $this->actingAs($user)
        ->patch('/planning/taxes/wallet', ['tax_wallet_id' => $walletB->id])
        ->assertRedirect();

    expect(taxPayment('tax:vat:2026-02')->template->wallet_id)->toBe($walletB->id);
});

test('planned and paid tax payments do not feed back into the VAT ledger', function () {
    $wallet = Wallet::factory()->create();
    vatIncome($wallet, 24);
    Transaction::factory()->create([
        'type' => 'expense', 'date' => '2026-03-31', 'invoice_date' => '2026-03-31',
        'net' => 24, 'vat_amount' => 0, 'wallet_id' => $wallet->id,
        'source' => 'tax', 'managed_key' => 'tax:vat:2026-02', 'is_reconciled' => true,
    ]);

    $feb = collect(VatLedger::monthly())->firstWhere('month', '2026-02');
    expect((float) $feb['income_vat'])->toBe(24.0);
    expect((float) $feb['expense_vat'])->toBe(0.0);
});

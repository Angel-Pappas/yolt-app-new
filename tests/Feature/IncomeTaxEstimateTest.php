<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\IncomeTaxYear;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\IncomeTaxEstimate;
use App\Support\IncomeTaxLedger;
use App\Support\TaxSync;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

/** The estimate of one fiscal year. */
function estimateFor(int $year): ?array
{
    return collect(IncomeTaxEstimate::years())->firstWhere('year', $year);
}

test('profit is income minus expenses and employer EFKA, by invoice date', function () {
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 100000, 'vat_amount' => 24000]);
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-04-01', 'net' => 30000]);
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-05-01', 'net' => 10000, 'efka_employer_amount' => 2179]);
    // Invoiced the year after: not 2026's.
    Transaction::factory()->create(['type' => 'income', 'date' => '2026-12-20', 'invoice_date' => '2027-01-02', 'net' => 5000]);

    $e = estimateFor(2026);

    expect($e['income'])->toBe(100000.0);
    expect($e['expenses'])->toBe(42179.0);
    expect($e['profit'])->toBe(57821.0);
    // 22 % of 57.821 = 12.720,62 + levy 1.000; advance 80 % of 12.720,62.
    expect($e['tax'])->toBe(13720.62);
    expect($e['advance'])->toBe(10176.5);
    expect($e['payable'])->toBe(23897.12);
});

test('it is paid in 8 monthly installments from July of the next year', function () {
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 100000]);

    $installments = estimateFor(2026)['installments'];

    expect($installments)->toHaveCount(8);
    expect($installments[0]['month'])->toBe('2027-07');
    expect($installments[0]['due_date'])->toBe('2027-07-30');
    expect($installments[7]['month'])->toBe('2028-02');
    expect(round(array_sum(array_column($installments, 'amount')), 2))->toBe(estimateFor(2026)['payable']);
});

test('what does not count leaves the profit', function () {
    $taxes = Category::factory()->create(['type' => 'expense', 'counts_for_profit' => false]);
    $shareholder = Entity::factory()->create(['type' => 'shareholder']);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 50000]);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 9000, 'is_excluded' => true]);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 8000, 'income_status' => 'dropped']);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 7000, 'entity_id' => $shareholder->id]);
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-03-01', 'net' => 6000, 'category_id' => $taxes->id]);
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-03-01', 'net' => 5000, 'source' => 'tax', 'managed_key' => 'tax:vat:2026-02']);
    Transaction::factory()->create(['type' => 'transfer', 'invoice_date' => '2026-03-01', 'net' => 4000, 'to_wallet_id' => Wallet::factory()->create()->id]);

    expect(estimateFor(2026)['profit'])->toBe(50000.0);
});

test('a loss pays only the business levy', function () {
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-03-01', 'net' => 5000]);

    $e = estimateFor(2026);
    expect($e['profit'])->toBe(-5000.0);
    expect($e['tax'])->toBe(1000.0);
    expect($e['advance'])->toBe(0.0);
});

test('the advance of one year is credited against the next', function () {
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 100000]);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2027-03-01', 'net' => 100000]);

    // 2026: 22.000 + 1.000 levy; advance 17.600.
    // 2027: the same tax and advance, less the 17.600 already advanced.
    expect(estimateFor(2026)['advance'])->toBe(17600.0);
    expect(estimateFor(2027)['credit'])->toBe(17600.0);
    expect(estimateFor(2027)['payable'])->toBe(23000.0);
});

test('an entered year replaces the estimate and passes on its advance', function () {
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2025-03-01', 'net' => 100000]);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 100000]);
    expect(estimateFor(2025))->not->toBeNull();

    // The accountant's figure for what is paid in 2026 (fiscal 2025).
    IncomeTaxYear::factory()->create([
        'year' => 2026, 'first_installment_month' => '2026-07-01', 'last_installment_month' => '2027-02-01',
        'monthly_installment_amount' => 1000, 'advance_amount' => 4000,
    ]);

    expect(estimateFor(2025))->toBeNull();
    expect(estimateFor(2026)['credit'])->toBe(4000.0);
});

test('estimated installments join the schedule and become tax payments', function () {
    Wallet::factory()->create();
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'date' => '2026-03-01', 'net' => 100000, 'vat_amount' => 0, 'is_reconciled' => true]);

    $rows = collect(IncomeTaxLedger::schedule())->where('estimated', true);
    expect($rows->where('year', 2026))->toHaveCount(8);

    TaxSync::run();

    $payment = Transaction::query()->where('managed_key', 'tax:income:2027-07')->sole();
    expect($payment->net)->toBe('5075.00'); // (23.000 + 17.600) ÷ 8
    // The payment itself never feeds back into the profit.
    TaxSync::run();
    expect($payment->refresh()->net)->toBe('5075.00');
});

test('two installments in one month are one payment', function () {
    IncomeTaxYear::factory()->create([
        'year' => 2026, 'first_installment_month' => '2026-07-01', 'last_installment_month' => '2027-08-01',
        'monthly_installment_amount' => 1000,
    ]);
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-03-01', 'net' => 100000]);

    $july = collect(IncomeTaxLedger::obligations())->where('period', '2027-07');
    expect($july)->toHaveCount(1);
    expect($july->first()->amount)->toBeGreaterThan(1000.0);
});

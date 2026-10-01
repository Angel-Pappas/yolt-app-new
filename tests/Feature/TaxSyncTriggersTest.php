<?php

use App\Models\IncomeTaxYear;
use App\Models\PublicHoliday;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\TaxSync;
use Illuminate\Support\Carbon;

// Income tax installments and public holidays both feed the generated tax payments
// (amounts and due dates), so editing either must re-sync them immediately — not
// leave them stale until the nightly `managed:sync`.

beforeEach(function () {
    Carbon::setTestNow('2026-03-10');
    // Generated tax payments need a wallet to be paid from.
    Wallet::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/** @return array<string, string> */
function incomeTaxYearPayload(string $amount): array
{
    return [
        'year' => '2025',
        'revenue_before_tax' => '100000',
        'total_tax' => '22000',
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-08-01',
        'monthly_installment_amount' => $amount,
    ];
}

/** @return array<string, float> managed_key => net */
function incomeTaxRows(): array
{
    return Transaction::where('managed_key', 'like', 'tax:income:%')
        ->orderBy('managed_key')
        ->pluck('net', 'managed_key')
        ->map(fn ($net): float => (float) $net)
        ->all();
}

function juneIncomeTaxDate(): string
{
    return Transaction::where('managed_key', 'tax:income:2026-06')->firstOrFail()->date->format('Y-m-d');
}

test('creating an income tax year generates its installment payments immediately', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/taxes/income', incomeTaxYearPayload('1000'))->assertRedirect();

    expect(incomeTaxRows())->toBe([
        'tax:income:2026-06' => 1000.0,
        'tax:income:2026-07' => 1000.0,
        'tax:income:2026-08' => 1000.0,
    ]);
});

test('editing an income tax year updates its installment payments immediately', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/taxes/income', incomeTaxYearPayload('1000'));
    $record = IncomeTaxYear::where('year', 2025)->firstOrFail();

    $this->actingAs($user)
        ->patch("/taxes/income/{$record->id}", incomeTaxYearPayload('1200'))
        ->assertRedirect();

    expect($record->refresh()->monthly_installment_amount)->toBe('1200.00');
    expect(incomeTaxRows())->toBe([
        'tax:income:2026-06' => 1200.0,
        'tax:income:2026-07' => 1200.0,
        'tax:income:2026-08' => 1200.0,
    ]);
});

test('deleting an income tax year removes its installment payments immediately', function () {
    $user = User::factory()->create();
    $record = IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-08-01',
    ]);
    TaxSync::run();
    expect(incomeTaxRows())->toHaveCount(3);

    $this->actingAs($user)->delete("/taxes/income/{$record->id}")->assertRedirect();

    expect(incomeTaxRows())->toBe([]);
});

test('public holiday changes move tax due dates immediately', function () {
    $user = User::factory()->create();
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-06-01',
    ]);
    TaxSync::run();
    // 30 June 2026 is a Tuesday: the month's last working day.
    expect(juneIncomeTaxDate())->toBe('2026-06-30');

    // A holiday on the 30th pulls the payment back to Monday the 29th.
    $this->actingAs($user)->post('/configuration/public-holidays', [
        'date' => '2026-06-30',
        'name' => 'Test holiday',
    ])->assertRedirect();
    expect(juneIncomeTaxDate())->toBe('2026-06-29');

    // Moving the holiday to the 29th frees the 30th again.
    $holiday = PublicHoliday::where('name', 'Test holiday')->firstOrFail();
    $this->actingAs($user)->patch("/configuration/public-holidays/{$holiday->id}", [
        'date' => '2026-06-29',
        'name' => 'Test holiday',
    ])->assertRedirect();
    expect($holiday->refresh()->date->format('Y-m-d'))->toBe('2026-06-29');
    expect(juneIncomeTaxDate())->toBe('2026-06-30');

    // Back on the 30th, then deleted: the payment returns to the 30th.
    $this->actingAs($user)->patch("/configuration/public-holidays/{$holiday->id}", [
        'date' => '2026-06-30',
        'name' => 'Test holiday',
    ]);
    expect(juneIncomeTaxDate())->toBe('2026-06-29');
    $this->actingAs($user)->delete("/configuration/public-holidays/{$holiday->id}")->assertRedirect();
    expect(juneIncomeTaxDate())->toBe('2026-06-30');
});

test('the nightly managed:sync command generates the tax payments', function () {
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-07-01',
        'monthly_installment_amount' => 500,
    ]);

    $this->artisan('managed:sync')->assertSuccessful();

    expect(incomeTaxRows())->toBe([
        'tax:income:2026-06' => 500.0,
        'tax:income:2026-07' => 500.0,
    ]);
});

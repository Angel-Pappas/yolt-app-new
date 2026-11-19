<?php

use App\Models\IncomeTaxYear;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

// Income tax installments and public holidays both feed the planned tax payments
// (amounts and due dates); the plan is computed live, so a change shows at once.

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

/** @return array<string, float> plan key => amount */
function incomeTaxRows(): array
{
    $rows = array_filter(taxPayments(), fn (string $key): bool => str_starts_with($key, 'tax:income:'), ARRAY_FILTER_USE_KEY);
    ksort($rows);

    return $rows;
}

function juneIncomeTaxDate(): string
{
    return taxPayment('tax:income:2026-06')->date()->format('Y-m-d');
}

test('creating an income tax year plans its installment payments at once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/planning/taxes/income', incomeTaxYearPayload('1000'))->assertRedirect();

    expect(incomeTaxRows())->toBe([
        'tax:income:2026-06' => 1000.0,
        'tax:income:2026-07' => 1000.0,
        'tax:income:2026-08' => 1000.0,
    ]);
});

test('editing an income tax year updates its installment payments at once', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/planning/taxes/income', incomeTaxYearPayload('1000'));
    $record = IncomeTaxYear::where('year', 2025)->firstOrFail();

    $this->actingAs($user)
        ->patch("/planning/taxes/income/{$record->id}", incomeTaxYearPayload('1200'))
        ->assertRedirect();

    expect($record->refresh()->monthly_installment_amount)->toBe('1200.00');
    expect(incomeTaxRows())->toBe([
        'tax:income:2026-06' => 1200.0,
        'tax:income:2026-07' => 1200.0,
        'tax:income:2026-08' => 1200.0,
    ]);
});

test('deleting an income tax year removes its installment payments at once', function () {
    $user = User::factory()->create();
    $record = IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-08-01',
    ]);
    expect(incomeTaxRows())->toHaveCount(3);

    $this->actingAs($user)->delete("/planning/taxes/income/{$record->id}")->assertRedirect();

    expect(incomeTaxRows())->toBe([]);
});

test('public holiday changes move tax due dates immediately', function () {
    $user = User::factory()->create();
    IncomeTaxYear::factory()->create([
        'first_installment_month' => '2026-06-01',
        'last_installment_month' => '2026-06-01',
    ]);
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

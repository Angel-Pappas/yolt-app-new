<?php

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;
use App\Support\RecurrenceGenerator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Fix "today" so the 24-month horizon is deterministic.
    Carbon::setTestNow('2026-01-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Add a dated period to a recurrence with the given amount lines — each line is
 * [amount, vat_rate_id|null, withheld_rate_id|null].
 *
 * @param  list<array<int, float|int|null>>  $lines
 */
function addPeriod(Recurrence $recurrence, string $start, ?string $end, array $lines, string $mode = 'net', int $position = 0): RecurrenceEntry
{
    $entry = RecurrenceEntry::factory()->create([
        'recurrence_id' => $recurrence->id,
        'start_date' => $start,
        'end_date' => $end,
        'net' => 0,
        'amount_mode' => $mode,
        'position' => $position,
    ]);
    foreach ($lines as $i => $line) {
        RecurrenceEntryLine::factory()->create([
            'recurrence_entry_id' => $entry->id,
            'amount' => $line[0],
            'vat_rate_id' => $line[1] ?? null,
            'withheld_rate_id' => $line[2] ?? null,
            'position' => $i,
        ]);
    }

    return $entry;
}

/** A monthly recurrence with a single one-line period. */
function monthlyRecurrence(array $overrides = [], float $net = 100): Recurrence
{
    $recurrence = Recurrence::factory()->create(array_merge([
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 1,
        'start_date' => '2026-01-01',
    ], $overrides));

    addPeriod($recurrence, '2026-01-01', null, [[$net]]);

    return $recurrence;
}

test('a monthly recurrence generates one transaction per month across the horizon', function () {
    $recurrence = monthlyRecurrence();

    RecurrenceGenerator::sync($recurrence);

    // Jan 2026 through Jan 2028 (today + 24 months = 2028-01-15) = 25 months.
    expect(Transaction::where('recurrence_id', $recurrence->id)->count())->toBe(25);
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-02-01")->exists())->toBeTrue();
    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-02-01")->value('net'))->toBe(100.0);
});

test('the amount in force follows the dated periods', function () {
    $recurrence = Recurrence::factory()->create([
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 1,
        'start_date' => '2026-01-01',
    ]);
    addPeriod($recurrence, '2026-01-01', '2026-12-31', [[100]]);
    addPeriod($recurrence, '2027-01-01', null, [[120]], position: 1);

    RecurrenceGenerator::sync($recurrence);

    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-06-01")->value('net'))->toBe(100.0);
    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2027-06-01")->value('net'))->toBe(120.0);
});

test('a later dated change supersedes an open-ended earlier period', function () {
    // Exactly the Capcut case: 100 from Jan (no end), then 200 from June (no end).
    $recurrence = Recurrence::factory()->create([
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 10,
        'start_date' => '2026-01-01',
    ]);
    addPeriod($recurrence, '2026-01-01', null, [[100]]);
    addPeriod($recurrence, '2026-06-01', null, [[200]], position: 1);

    RecurrenceGenerator::sync($recurrence);

    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-05-10")->value('net'))->toBe(100.0);
    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-06-10")->value('net'))->toBe(200.0);
    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-07-10")->value('net'))->toBe(200.0);
});

test('VAT and withholding are computed from the current rates', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    $recurrence = Recurrence::factory()->create(['start_date' => '2026-01-01', 'day_of_month' => 1]);
    addPeriod($recurrence, '2026-01-01', null, [[100, $vat->id, $withheld->id]]);

    RecurrenceGenerator::sync($recurrence);

    $row = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-03-01")->first();
    expect((float) $row->vat_amount)->toBe(24.0);
    expect((float) $row->withheld_amount)->toBe(20.0);
    expect($row->vat_rate_id)->toBe($vat->id);
    expect($row->vatLines()->count())->toBe(1);
    expect($row->withheldLines()->count())->toBe(1);
});

test('a period with several lines generates the full VAT and withholding breakdown', function () {
    // The contractor case: one line with withholding, one without.
    $vat24 = VatRate::factory()->create(['rate' => 24]);
    $vat6 = VatRate::factory()->create(['rate' => 6]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    $recurrence = Recurrence::factory()->create(['start_date' => '2026-01-01', 'day_of_month' => 1]);
    addPeriod($recurrence, '2026-01-01', null, [
        [1000, $vat24->id, $withheld->id],
        [300, $vat6->id],
    ]);

    RecurrenceGenerator::sync($recurrence);

    $row = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-02-01")->first();
    expect((float) $row->net)->toBe(1300.0);
    expect((float) $row->vat_amount)->toBe(258.0); // 240 + 18
    expect((float) $row->withheld_amount)->toBe(200.0);
    expect($row->vat_rate_id)->toBeNull(); // mixed rates
    expect($row->vatLines()->orderBy('position')->pluck('vat_amount')->map(fn ($v) => (float) $v)->all())->toBe([240.0, 18.0]);
    $w = $row->withheldLines()->get();
    expect($w)->toHaveCount(1);
    expect($w[0]->position)->toBe(0);
    expect((float) $w[0]->net)->toBe(1000.0);
});

test('each period carries its own lines and amounts over time', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    $recurrence = Recurrence::factory()->create(['start_date' => '2026-01-01', 'day_of_month' => 1]);
    addPeriod($recurrence, '2026-01-01', '2026-12-31', [[1000, $vat->id, $withheld->id], [300, $vat->id]]);
    addPeriod($recurrence, '2027-01-01', null, [[1100, $vat->id, $withheld->id], [350, $vat->id]], position: 1);

    RecurrenceGenerator::sync($recurrence);

    $y1 = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-12-01")->first();
    $y2 = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2027-01-01")->first();
    expect((float) $y1->net)->toBe(1300.0);
    expect((float) $y1->withheld_amount)->toBe(200.0);
    expect((float) $y2->net)->toBe(1450.0);
    expect((float) $y2->withheld_amount)->toBe(220.0);
});

test('a Total-mode period reconstructs the exact typed total, like the transaction form', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    $recurrence = Recurrence::factory()->create(['start_date' => '2026-01-01', 'day_of_month' => 1]);
    addPeriod($recurrence, '2026-01-01', null, [[29.99, $vat->id], [123.45, $vat->id, $withheld->id]], mode: 'total');

    RecurrenceGenerator::sync($recurrence);

    $row = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-01-01")->first();
    $cash = round((float) $row->net + (float) $row->vat_amount - (float) $row->withheld_amount, 2);
    expect($cash)->toBe(153.44);
});

test('a payroll recurrence stores the FMY and EFKA amounts', function () {
    $recurrence = Recurrence::factory()->create([
        'is_payroll' => true,
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 25,
        'start_date' => '2026-01-01',
    ]);
    RecurrenceEntry::factory()->create([
        'recurrence_id' => $recurrence->id,
        'start_date' => '2026-01-01',
        'net' => 1000,
        'fmy_amount' => 80,
        'efka_employee_amount' => 150,
        'efka_employer_amount' => 250,
    ]);

    RecurrenceGenerator::sync($recurrence);

    $row = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-01-25")->first();
    expect((float) $row->net)->toBe(1000.0);
    expect((float) $row->fmy_amount)->toBe(80.0);
    expect((float) $row->efka_employee_amount)->toBe(150.0);
    expect((float) $row->efka_employer_amount)->toBe(250.0);
    expect((float) $row->vat_amount)->toBe(0.0);
});

test('re-syncing after an amount change updates unreconciled rows but freezes reconciled ones', function () {
    $recurrence = monthlyRecurrence(net: 100);
    RecurrenceGenerator::sync($recurrence);

    // Reconcile one occurrence with a corrected amount.
    $frozen = Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-03-01")->first();
    $frozen->update(['is_reconciled' => true, 'net' => 111]);

    // Raise the amount and re-sync.
    RecurrenceEntryLine::query()->update(['amount' => 200]);
    RecurrenceGenerator::sync($recurrence->fresh());

    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-04-01")->value('net'))->toBe(200.0);
    // The reconciled row keeps its corrected amount.
    expect((float) Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-03-01")->value('net'))->toBe(111.0);
});

test('shortening the end date removes future unreconciled occurrences', function () {
    $recurrence = monthlyRecurrence();
    RecurrenceGenerator::sync($recurrence);
    expect(Transaction::where('recurrence_id', $recurrence->id)->count())->toBe(25);

    $recurrence->update(['end_date' => '2026-06-30']);
    RecurrenceGenerator::sync($recurrence->fresh());

    // Jan–Jun 2026 = 6 occurrences remain.
    expect(Transaction::where('recurrence_id', $recurrence->id)->count())->toBe(6);
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-07-01")->exists())->toBeFalse();
});

test('deactivating a recurrence removes its unreconciled rows', function () {
    $recurrence = monthlyRecurrence();
    RecurrenceGenerator::sync($recurrence);

    $recurrence->update(['active' => false]);
    RecurrenceGenerator::sync($recurrence->fresh());

    expect(Transaction::where('recurrence_id', $recurrence->id)->count())->toBe(0);
});

test('the day-of-month clamps on short months without drifting', function () {
    $recurrence = Recurrence::factory()->create([
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 31,
        'start_date' => '2026-01-31',
        'end_date' => '2026-04-30',
    ]);
    addPeriod($recurrence, '2026-01-01', null, [[50]]);

    RecurrenceGenerator::sync($recurrence);

    $keys = Transaction::where('recurrence_id', $recurrence->id)
        ->orderBy('date')->pluck('managed_key')->all();

    expect($keys)->toBe([
        "recurrence:{$recurrence->id}:2026-01-31",
        "recurrence:{$recurrence->id}:2026-02-28",
        "recurrence:{$recurrence->id}:2026-03-31",
        "recurrence:{$recurrence->id}:2026-04-30",
    ]);
});

test('the managed floor excludes occurrences dated before it', function () {
    Setting::current()->update(['managed_from' => '2026-09-01']);
    $recurrence = monthlyRecurrence(); // start 2026-01-01, monthly on the 1st

    RecurrenceGenerator::sync($recurrence);

    expect(Transaction::where('recurrence_id', $recurrence->id)->where('date', '<', '2026-09-01')->count())->toBe(0);
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-09-01")->exists())->toBeTrue();
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-08-01")->exists())->toBeFalse();
});

test('setting a floor purges pre-floor unreconciled rows but keeps reconciled ones', function () {
    $recurrence = monthlyRecurrence();
    RecurrenceGenerator::sync($recurrence); // no floor yet — generates from 2026-01

    Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-03-01")
        ->first()->update(['is_reconciled' => true]);

    Setting::current()->update(['managed_from' => '2026-09-01']);
    RecurrenceGenerator::sync($recurrence->fresh());

    // Feb (unreconciled, pre-floor) is gone; March (reconciled) is kept as history.
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-02-01")->exists())->toBeFalse();
    expect(Transaction::where('managed_key', "recurrence:{$recurrence->id}:2026-03-01")->exists())->toBeTrue();
});

test('a weekly recurrence steps by whole weeks from the start date', function () {
    $recurrence = Recurrence::factory()->create([
        'interval_count' => 2,
        'interval_unit' => 'week',
        'day_of_month' => null,
        'start_date' => '2026-01-05',
        'end_date' => '2026-02-05',
    ]);
    addPeriod($recurrence, '2026-01-01', null, [[30]]);

    RecurrenceGenerator::sync($recurrence);

    $keys = Transaction::where('recurrence_id', $recurrence->id)
        ->orderBy('date')->pluck('managed_key')->all();

    expect($keys)->toBe([
        "recurrence:{$recurrence->id}:2026-01-05",
        "recurrence:{$recurrence->id}:2026-01-19",
        "recurrence:{$recurrence->id}:2026-02-02",
    ]);
});

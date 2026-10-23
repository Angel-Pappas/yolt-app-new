<?php

use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use App\Support\RecurrenceGenerator;
use App\Support\RecurringOverview;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A recurrence with one entity of the given type, plus a period per
 * [start, end, amount lines], each line [amount, vat_rate_id|null, withheld_rate_id|null].
 *
 * @param  list<array{0: string, 1: string|null, 2: list<array<int, float|int|null>>}>  $periods
 */
function overviewRecurrence(string $entityType, array $periods, array $overrides = []): Recurrence
{
    $recurrence = Recurrence::factory()->create(array_merge([
        'entity_id' => Entity::factory()->create(['type' => $entityType])->id,
        'start_date' => $periods[0][0],
        'end_date' => $periods[array_key_last($periods)][1],
    ], $overrides));

    foreach ($periods as $i => [$start, $end, $lines]) {
        $entry = RecurrenceEntry::factory()->create([
            'recurrence_id' => $recurrence->id,
            'start_date' => $start,
            'end_date' => $end,
            'net' => 0,
            'position' => $i,
        ]);
        foreach ($lines as $j => $line) {
            RecurrenceEntryLine::factory()->create([
                'recurrence_entry_id' => $entry->id,
                'amount' => $line[0],
                'vat_rate_id' => $line[1] ?? null,
                'withheld_rate_id' => $line[2] ?? null,
                'position' => $j,
            ]);
        }
    }

    return $recurrence;
}

/** The overview row for one recurrence. */
function overviewRow(Recurrence $recurrence): array
{
    return collect(RecurringOverview::rows())->firstWhere('id', $recurrence->id);
}

test('recurrences are grouped by who they are with', function () {
    $employee = overviewRecurrence('employee', [['2026-01-01', null, []]], ['is_payroll' => true]);
    $contractor = overviewRecurrence('contractor', [['2026-01-01', null, [[100]]]]);
    $supplier = overviewRecurrence('supplier', [['2026-01-01', null, [[100]]]]);
    $shareholder = overviewRecurrence('shareholder', [['2026-01-01', null, [[100]]]]);
    $customer = overviewRecurrence('customer', [['2026-01-01', null, [[100]]]], ['type' => 'income']);

    expect(overviewRow($employee)['group'])->toBe('payroll');
    expect(overviewRow($contractor)['group'])->toBe('contractors');
    expect(overviewRow($supplier)['group'])->toBe('expenses');
    expect(overviewRow($shareholder)['group'])->toBe('expenses');
    expect(overviewRow($customer)['group'])->toBe('income');
});

test('a row shows the VAT, withholding and cash total of its lines', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    $recurrence = overviewRecurrence('contractor', [
        ['2026-01-01', null, [[1000, $vat->id, $withheld->id], [50, $vat->id, null]]],
    ]);

    $row = overviewRow($recurrence);

    expect($row['net'])->toBe(1050.0);
    expect($row['vat'])->toBe(252.0);
    expect($row['withheld'])->toBe(200.0);
    expect($row['total'])->toBe(1102.0); // 1050 + 252 − 200
});

test('a payroll row shows net, FMY, EFKA, what is paid and what it costs', function () {
    $recurrence = overviewRecurrence('employee', [['2026-01-01', null, []]], ['is_payroll' => true]);
    $recurrence->entries()->first()->update([
        'net' => 1500,
        'fmy_amount' => 120.50,
        'efka_employee_amount' => 200,
        'efka_employer_amount' => 330.25,
    ]);

    $row = overviewRow($recurrence);

    expect($row['net'])->toBe(1500.0);
    expect($row['fmy'])->toBe(120.5);
    expect($row['efka_employee'])->toBe(200.0);
    expect($row['efka_employer'])->toBe(330.25);
    expect($row['total'])->toBe(1179.5); // To pay: 1500 − 120,50 − 200
    expect($row['cost'])->toBe(1830.25); // 1500 + 330,25
    expect($row['vat'])->toBe(0.0);
});

test('the amounts come from the period in force today', function () {
    $recurrence = overviewRecurrence('supplier', [
        ['2026-01-01', null, [[100]]],
        ['2026-06-01', null, [[200]]],
        ['2026-09-01', null, [[300]]],
    ]);

    expect(overviewRow($recurrence)['net'])->toBe(200.0);
});

test('a recurrence that has not started shows its first period', function () {
    $recurrence = overviewRecurrence('supplier', [
        ['2026-08-01', '2026-12-31', [[150]]],
        ['2027-01-01', null, [[175]]],
    ]);

    expect(overviewRow($recurrence)['net'])->toBe(150.0);
});

test('an ended recurrence shows its last period', function () {
    $recurrence = overviewRecurrence('supplier', [
        ['2025-01-01', '2025-06-30', [[80]]],
        ['2025-07-01', '2026-03-31', [[90]]],
    ]);

    expect(overviewRow($recurrence)['net'])->toBe(90.0);
});

test('a row carries the entity and the date range', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-02-01', '2027-01-31', [[100]]]]);

    $row = overviewRow($recurrence);

    expect($row['entity'])->toBe([
        'id' => $recurrence->entity_id,
        'name' => $recurrence->entity->name,
        'type' => 'supplier',
    ]);
    expect($row['start_date'])->toBe('2026-02-01');
    expect($row['end_date'])->toBe('2027-01-31');
});

test('a row carries the full recurrence for the edit form', function () {
    $recurrence = overviewRecurrence('supplier', [
        ['2026-01-01', '2026-05-31', [[100]]],
        ['2026-06-01', null, [[120]]],
    ]);

    $record = overviewRow($recurrence)['recurrence'];

    expect($record['id'])->toBe($recurrence->id);
    expect($record['wallet_id'])->toBe($recurrence->wallet_id);
    expect($record['entries'])->toHaveCount(2);
    expect($record['entries'][1]['lines'][0]['amount'])->toBe('120.00');
    expect($record)->not->toHaveKey('entity');
});

test('an open-ended recurrence has no end date', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-02-01', null, [[100]]]]);

    expect(overviewRow($recurrence)['end_date'])->toBeNull();
});

test('an open-ended recurrence is never done, even with everything reconciled', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-01-01', null, [[100]]]]);
    RecurrenceGenerator::sync($recurrence);
    Transaction::where('recurrence_id', $recurrence->id)->update(['is_reconciled' => true]);

    expect(overviewRow($recurrence)['done'])->toBeFalse();
});

test('an ended recurrence is done once its last transaction is reconciled', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-01-01', '2026-08-31', [[100]]]]);
    RecurrenceGenerator::sync($recurrence);
    $rows = Transaction::where('recurrence_id', $recurrence->id)->orderBy('date')->get();

    // Last one (August) still open: active.
    $rows->slice(0, -1)->each->update(['is_reconciled' => true]);
    expect(overviewRow($recurrence)['done'])->toBeFalse();

    $rows->last()->update(['is_reconciled' => true]);
    expect(overviewRow($recurrence)['done'])->toBeTrue();
});

test('an ended recurrence that never generated a transaction is done', function () {
    // Entirely before the managed floor, so nothing was generated.
    Setting::current()->update(['managed_from' => '2026-09-01']);
    $recurrence = overviewRecurrence('supplier', [['2026-01-01', '2026-03-31', [[100]]]]);
    RecurrenceGenerator::sync($recurrence);

    expect(Transaction::where('recurrence_id', $recurrence->id)->count())->toBe(0);
    expect(overviewRow($recurrence)['done'])->toBeTrue();
});

test('a deleted recurrence is not listed', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-02-01', null, [[100]]]]);
    $recurrence->delete();

    expect(RecurringOverview::rows())->toBeEmpty();
});

test('an active user sees the Recurring page', function () {
    overviewRecurrence('supplier', [['2026-02-01', null, [[100]]]]);

    $this->actingAs(User::factory()->create())
        ->get('/recurring')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('recurring/index')
                ->has('recurrences', 1)
                ->where('recurrences.0.group', 'expenses'),
        );
});

test('the Recurring page deep-links a recurrence into its edit form', function () {
    $recurrence = overviewRecurrence('supplier', [['2026-02-01', null, [[100]]]]);

    $this->actingAs(User::factory()->create())
        ->get("/recurring?edit={$recurrence->id}")
        ->assertInertia(fn ($page) => $page->where('editId', $recurrence->id));
});

test('a recurrence can be added from the Recurring page by picking its entity', function () {
    $wallet = Wallet::factory()->create();
    $contractor = Entity::factory()->contractor()->create();

    $this->actingAs(User::factory()->create())
        ->from('/recurring')
        ->post('/recurrences', [
            'entity_id' => $contractor->id,
            'type' => 'expense',
            'wallet_id' => $wallet->id,
            'interval_count' => 1,
            'interval_unit' => 'month',
            'day_of_month' => 1,
            'entries' => [[
                'start_date' => '2026-07-01',
                'amount_mode' => 'net',
                'lines' => [['amount' => 500]],
            ]],
        ])
        ->assertRedirect('/recurring');

    $recurrence = Recurrence::where('entity_id', $contractor->id)->sole();
    expect(overviewRow($recurrence)['group'])->toBe('contractors');
    // It is the entity's own recurrence, so its page lists it too.
    expect($contractor->recurrences()->pluck('id')->all())->toBe([$recurrence->id]);
});

test('adding a recurrence without an entity is refused', function () {
    $wallet = Wallet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/recurrences', [
            'type' => 'expense',
            'wallet_id' => $wallet->id,
            'interval_count' => 1,
            'interval_unit' => 'month',
            'entries' => [['start_date' => '2026-07-01', 'amount_mode' => 'net', 'lines' => [['amount' => 5]]]],
        ])
        ->assertSessionHasErrors('entity_id');

    expect(Recurrence::count())->toBe(0);
});

test('a guest cannot see the Recurring page', function () {
    $this->get('/recurring')->assertRedirect(route('login'));
});

<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

function recurrencePayload(Entity $entity, Wallet $wallet, array $overrides = []): array
{
    return array_merge([
        'entity_id' => $entity->id,
        'type' => 'expense',
        'description' => 'Rent',
        'wallet_id' => $wallet->id,
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 1,
        'entries' => [
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'amount_mode' => 'net',
                'lines' => [['amount' => 100, 'vat_rate_id' => null, 'withheld_rate_id' => null]],
            ],
        ],
    ], $overrides);
}

test('creating a recurrence generates its transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)
        ->post('/recurrences', recurrencePayload($entity, $wallet))
        ->assertRedirect();

    expect(Recurrence::count())->toBe(1);
    // Jan–Jun 2026 = 6 monthly occurrences.
    expect(Transaction::where('source', 'recurrence')->count())->toBe(6);
    expect((float) Transaction::where('source', 'recurrence')->orderBy('date')->first()->net)->toBe(100.0);
});

test('the recurrence window is derived from its periods', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $line = [['amount' => 100]];
    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet, [
        // Submitted out of order on purpose; the last (by start) is open-ended.
        'entries' => [
            ['start_date' => '2027-01-01', 'end_date' => null, 'amount_mode' => 'net', 'lines' => $line],
            ['start_date' => '2026-03-01', 'end_date' => '2026-12-31', 'amount_mode' => 'net', 'lines' => $line],
        ],
    ]))->assertRedirect();

    $recurrence = Recurrence::with('entries')->first();
    expect($recurrence->start_date->toDateString())->toBe('2026-03-01');
    expect($recurrence->end_date)->toBeNull();
    expect($recurrence->entries->pluck('start_date')->map->toDateString()->all())->toBe(['2026-03-01', '2027-01-01']);
});

test('a period stores several VAT lines with per-line withholding and its Net/Total mode', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->contractor()->create();
    $wallet = Wallet::factory()->create();
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet, [
        'entries' => [[
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'amount_mode' => 'net',
            'lines' => [
                ['amount' => 1000, 'vat_rate_id' => $vat->id, 'withheld_rate_id' => $withheld->id],
                ['amount' => 300, 'vat_rate_id' => $vat->id, 'withheld_rate_id' => null],
            ],
        ]],
    ]))->assertRedirect();

    $entry = Recurrence::first()->entries()->with('lines')->first();
    expect($entry->amount_mode)->toBe('net');
    expect($entry->lines)->toHaveCount(2);
    expect($entry->lines[0]->withheld_rate_id)->toBe($withheld->id);
    expect($entry->lines[1]->withheld_rate_id)->toBeNull();

    $row = Transaction::where('source', 'recurrence')->first();
    expect((float) $row->net)->toBe(1300.0);
    expect((float) $row->vat_amount)->toBe(312.0);
    expect((float) $row->withheld_amount)->toBe(200.0);
});

test('a non-payroll period requires at least one amount line', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)
        ->post('/recurrences', recurrencePayload($entity, $wallet, [
            'entries' => [['start_date' => '2026-01-01', 'amount_mode' => 'net', 'lines' => []]],
        ]))
        ->assertSessionHasErrors('entries.0.lines');
});

test('a recurrence requires at least one period', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)
        ->post('/recurrences', recurrencePayload($entity, $wallet, ['entries' => []]))
        ->assertSessionHasErrors('entries');
});

test('a period cannot end before it starts', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)
        ->post('/recurrences', recurrencePayload($entity, $wallet, [
            'entries' => [['start_date' => '2026-05-01', 'end_date' => '2026-04-30', 'amount_mode' => 'net', 'lines' => [['amount' => 1]]]],
        ]))
        ->assertSessionHasErrors('entries.0.end_date');
});

test('the Payroll category makes a recurrence payroll, taking Net/FMY/EFKA per period', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->employee()->create();
    $wallet = Wallet::factory()->create();
    $payroll = Category::factory()->create(['name' => 'Payroll', 'type' => 'expense']);

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet, [
        'category_id' => $payroll->id,
        'entries' => [[
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'net' => 1000,
            'fmy_amount' => 80,
            'efka_employee_amount' => 150,
            'efka_employer_amount' => 250,
        ]],
    ]))->assertRedirect();

    $recurrence = Recurrence::first();
    expect($recurrence->is_payroll)->toBeTrue();
    $row = Transaction::where('source', 'recurrence')->first();
    expect((float) $row->net)->toBe(1000.0);
    expect((float) $row->fmy_amount)->toBe(80.0);
    expect((float) $row->vat_amount)->toBe(0.0);
});

test('updating a recurrence re-generates its unreconciled transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet));
    $recurrence = Recurrence::first();

    $this->actingAs($user)
        ->patch("/recurrences/{$recurrence->id}", recurrencePayload($entity, $wallet, [
            'entries' => [['start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'amount_mode' => 'net', 'lines' => [['amount' => 250]]]],
        ]))
        ->assertRedirect();

    expect((float) Transaction::where('source', 'recurrence')->orderBy('date')->first()->net)->toBe(250.0);
});

test('the entity of a recurrence cannot be changed', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $other = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet));
    $recurrence = Recurrence::first();

    $this->actingAs($user)
        ->patch("/recurrences/{$recurrence->id}", recurrencePayload($other, $wallet))
        ->assertRedirect();

    expect($recurrence->fresh()->entity_id)->toBe($entity->id);
});

test('deleting a recurrence removes its unreconciled transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet));
    $recurrence = Recurrence::first();

    $this->actingAs($user)
        ->delete("/recurrences/{$recurrence->id}")
        ->assertRedirect();

    expect(Transaction::where('source', 'recurrence')->count())->toBe(0);
    expect(Recurrence::withTrashed()->count())->toBe(1);
});

test('a guest cannot create a recurrence', function () {
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->post('/recurrences', recurrencePayload($entity, $wallet))
        ->assertRedirect(route('login'));
});

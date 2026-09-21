<?php

use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
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
        'is_payroll' => false,
        'interval_count' => 1,
        'interval_unit' => 'month',
        'day_of_month' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'entries' => [
            ['start_date' => '2026-01-01', 'net' => 100],
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

test('a recurrence requires at least one amount entry', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)
        ->post('/recurrences', recurrencePayload($entity, $wallet, ['entries' => []]))
        ->assertSessionHasErrors('entries');
});

test('updating a recurrence re-generates its unreconciled transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create();
    $wallet = Wallet::factory()->create();

    $this->actingAs($user)->post('/recurrences', recurrencePayload($entity, $wallet));
    $recurrence = Recurrence::first();

    $this->actingAs($user)
        ->patch("/recurrences/{$recurrence->id}", recurrencePayload($entity, $wallet, [
            'entries' => [['start_date' => '2026-01-01', 'net' => 250]],
        ]))
        ->assertRedirect();

    expect((float) Transaction::where('source', 'recurrence')->orderBy('date')->first()->net)->toBe(250.0);
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

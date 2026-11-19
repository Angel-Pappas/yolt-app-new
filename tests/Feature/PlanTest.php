<?php

use App\Models\Entity;
use App\Models\PlanAdjustment;
use App\Models\PlannedTransaction;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Plan;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-19');
});

afterEach(function () {
    Carbon::setTestNow();
});

function plannedExpense(Wallet $wallet, string $date, float $net = 2000, array $extra = []): PlannedTransaction
{
    return PlannedTransaction::query()->create(array_merge([
        'type' => 'expense', 'date' => $date, 'invoice_date' => $date, 'description' => 'One-off',
        'net' => $net, 'vat_amount' => 0, 'withheld_amount' => 0, 'wallet_id' => $wallet->id,
        'entity_id' => Entity::factory()->supplier()->create()->id, 'is_one_off' => true,
    ], $extra));
}

test('planned rows are never part of the books', function () {
    $wallet = Wallet::factory()->create();
    plannedExpense($wallet, '2026-12-15');

    expect(Transaction::query()->count())->toBe(0);
    expect(PlannedTransaction::query()->count())->toBe(1);
    $this->actingAs(User::factory()->create())->get('/transactions?all=1')
        ->assertInertia(fn ($page) => $page->has('transactions', 0));
});

test('an item paid in part stays open for the rest; paid in full it is done', function () {
    $wallet = Wallet::factory()->create();
    $planned = plannedExpense($wallet, '2026-12-15');
    $pay = fn (float $net) => Transaction::factory()->create([
        'type' => 'expense', 'date' => '2026-12-14', 'invoice_date' => '2026-12-14', 'net' => $net,
        'vat_amount' => 0, 'withheld_amount' => 0, 'wallet_id' => $wallet->id, 'managed_key' => $planned->planKey(),
    ]);

    $pay(1000);
    expect(Plan::find($planned->planKey())->open())->toBe(1000.0);
    // The plan adds the 1000 still expected to the real 1000 — never the whole 2000.
    expect(Plan::counted(withTaxes: false)->sum(fn ($t) => (float) $t->net))->toBe(1000.0);

    $pay(1000);
    expect(Plan::find($planned->planKey())->isDone())->toBeTrue();
    expect(Plan::counted(withTaxes: false))->toHaveCount(0);
});

test('an item nothing paid by its wallet\'s concluded date is not matched yet and counts from today', function () {
    $wallet = Wallet::factory()->create(['concluded_until' => '2026-10-31']);
    $planned = plannedExpense($wallet, '2026-10-15');
    plannedExpense($wallet, '2026-11-25'); // after the concluded date: as planned

    expect(Plan::notMatched()->keys()->all())->toBe([$planned->planKey()]);
    $counted = Plan::counted()->firstWhere('managed_key', $planned->planKey());
    expect($counted->date->toDateString())->toBe('2026-11-19');
    expect($counted->invoice_date->toDateString())->toBe('2026-11-19');

    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn ($page) => $page->where('attention.not_matched', 1)->where('not_matched', 1));
});

test('a computed item can be moved or cancelled by hand', function () {
    $wallet = Wallet::factory()->create(['concluded_until' => '2026-10-31']);
    $supplier = Entity::factory()->supplier()->create();
    $recurrence = Recurrence::factory()->create([
        'entity_id' => $supplier->id, 'wallet_id' => $wallet->id, 'interval_count' => 1, 'interval_unit' => 'month',
        'day_of_month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31',
    ]);
    $entry = RecurrenceEntry::factory()->create(['recurrence_id' => $recurrence->id, 'start_date' => '2026-10-01', 'end_date' => null, 'net' => 0]);
    RecurrenceEntryLine::factory()->create(['recurrence_entry_id' => $entry->id, 'amount' => 300, 'position' => 0]);
    $october = "recurrence:{$recurrence->id}:2026-10-10";
    expect(Plan::notMatched()->keys()->all())->toBe([$october]);

    PlanAdjustment::query()->create(['plan_key' => $october, 'date' => '2026-11-30']);
    Plan::flush();
    expect(Plan::notMatched())->toHaveCount(0);
    expect(Plan::find($october)->date()->toDateString())->toBe('2026-11-30');

    PlanAdjustment::query()->where('plan_key', $october)->update(['cancelled' => true]);
    Plan::flush();
    expect(Plan::find($october)->isDone())->toBeTrue();
    expect(Plan::counted()->firstWhere('managed_key', $october))->toBeNull();
});

test('a hand-typed transaction can say which planned item it pays', function () {
    $wallet = Wallet::factory()->create();
    $planned = plannedExpense($wallet, '2026-11-25', 500);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense', 'date' => '2026-11-18', 'invoice_date' => '2026-11-18', 'wallet_id' => $wallet->id,
        'entity_id' => $planned->entity_id, 'amount_mode' => 'net', 'lines' => [['amount' => '500']],
        'managed_key' => $planned->planKey(),
    ])->assertRedirect();

    expect(Transaction::query()->sole()->managed_key)->toBe($planned->planKey());
    expect(Plan::find($planned->planKey())->isDone())->toBeTrue();

    // A key the plan doesn't know is refused.
    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense', 'date' => '2026-11-18', 'invoice_date' => '2026-11-18', 'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '5']], 'managed_key' => 'planned:999',
    ])->assertSessionHasErrors('managed_key');
});

test('the plan read back from the cache is the plan built fresh', function () {
    $wallet = Wallet::factory()->create(['concluded_until' => '2026-10-31']);
    plannedExpense($wallet, '2026-10-15');
    plannedExpense($wallet, '2026-12-15', 300, ['vat_amount' => 72]);
    $snapshot = fn () => Plan::items()->map(fn ($i) => [
        $i->key, $i->kind, $i->open(), $i->overdue, $i->date()->toDateString(),
        $i->template->entity?->name, (float) $i->template->vat_amount, $i->planned()?->id,
    ])->values()->all();

    $fresh = $snapshot();
    // A new request: the memory is empty, the cache still holds this version.
    $memo = new ReflectionClass(Plan::class);
    $memo->getProperty('nonTax')->setValue(null, null);
    $memo->getProperty('tax')->setValue(null, null);

    expect($snapshot())->toBe($fresh);
});

test('a recurrence occurrence is settled by its payment, a few cents off or not', function () {
    $wallet = Wallet::factory()->create();
    $supplier = Entity::factory()->supplier()->create();
    $recurrence = Recurrence::factory()->create([
        'entity_id' => $supplier->id, 'wallet_id' => $wallet->id, 'interval_count' => 1, 'interval_unit' => 'month',
        'day_of_month' => 5, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
    ]);
    $entry = RecurrenceEntry::factory()->create(['recurrence_id' => $recurrence->id, 'start_date' => '2026-11-01', 'end_date' => null, 'net' => 0]);
    RecurrenceEntryLine::factory()->create(['recurrence_entry_id' => $entry->id, 'amount' => 54, 'position' => 0]);
    $key = "recurrence:{$recurrence->id}:2026-11-05";
    Transaction::factory()->create([
        'type' => 'expense', 'date' => '2026-11-05', 'invoice_date' => '2026-11-05', 'net' => 53.10,
        'vat_amount' => 0, 'withheld_amount' => 0, 'wallet_id' => $wallet->id, 'managed_key' => $key, 'is_reconciled' => true,
    ]);

    expect(Plan::find($key)->isDone())->toBeTrue();

    // Split on purpose at the bank: the rest stays open.
    PlanAdjustment::query()->create(['plan_key' => $key, 'date' => '2026-12-05', 'part_paid' => true]);
    Plan::flush();
    expect(Plan::find($key)->open())->toBe(0.9);
});

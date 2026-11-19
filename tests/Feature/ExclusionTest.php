<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\VatLedger;
use App\Support\WalletBalances;
use App\Support\WithheldLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

test('an excluded transaction counts for no tax', function () {
    Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-10-10', 'net' => 1000, 'vat_amount' => 240]);
    $off = Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-10-12', 'net' => 500, 'vat_amount' => 120]);

    expect(collect(VatLedger::monthly())->firstWhere('month', '2026-10')['income_vat'])->toBe(360.0);

    $off->update(['is_excluded' => true]);

    expect(collect(VatLedger::monthly())->firstWhere('month', '2026-10')['income_vat'])->toBe(240.0);
});

test('a dropped income counts for no tax, an invoiced or paid one does', function () {
    $t = Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-10-10', 'net' => 1000, 'vat_amount' => 240, 'income_status' => 'invoiced']);
    expect(collect(VatLedger::monthly())->firstWhere('month', '2026-10')['income_vat'])->toBe(240.0);

    $t->update(['income_status' => 'dropped']);

    expect(VatLedger::obligations())->toBe([]);
});

test('an excluded expense leaves the withholding ledger', function () {
    Transaction::factory()->create(['type' => 'expense', 'invoice_date' => '2026-10-10', 'net' => 1000, 'withheld_amount' => 200, 'is_excluded' => true]);

    expect(WithheldLedger::obligations())->toBe([]);
});

test('an excluded row moves no wallet balance but stays in the balance view', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 100]);
    Transaction::factory()->create(['type' => 'income', 'wallet_id' => $wallet->id, 'date' => '2026-10-01', 'net' => 50, 'vat_amount' => 0, 'is_reconciled' => true]);
    Transaction::factory()->create(['type' => 'income', 'wallet_id' => $wallet->id, 'date' => '2026-10-02', 'net' => 70, 'vat_amount' => 0, 'is_excluded' => true]);

    expect(WalletBalances::all()[$wallet->id])->toBe(150.0);

    $rows = WalletBalances::runningFor($wallet->id, 100.0);
    expect($rows)->toHaveCount(2);
    expect($rows->last()->balance)->toBe(150.0);
});

test('a transaction can be excluded and included again from its row', function () {
    $t = Transaction::factory()->create(['type' => 'expense']);
    $user = User::factory()->create();

    $this->actingAs($user)->patch("/transactions/{$t->id}/excluded", ['excluded' => true])->assertRedirect();
    expect($t->refresh()->is_excluded)->toBeTrue();

    $this->actingAs($user)->patch("/transactions/{$t->id}/excluded", ['excluded' => false])->assertRedirect();
    expect($t->refresh()->is_excluded)->toBeFalse();
});

test('a reconciled transaction cannot be excluded', function () {
    $t = Transaction::factory()->create(['type' => 'expense', 'is_reconciled' => true]);

    $this->actingAs(User::factory()->create())
        ->patch("/transactions/{$t->id}/excluded", ['excluded' => true])
        ->assertSessionHasErrors('excluded');

    expect($t->refresh()->is_excluded)->toBeFalse();
});

test('a tax payment cannot be excluded', function () {
    $t = Transaction::factory()->create(['type' => 'expense', 'source' => 'tax', 'managed_key' => 'tax:vat:2026-10']);

    $this->actingAs(User::factory()->create())
        ->patch("/transactions/{$t->id}/excluded", ['excluded' => true])
        ->assertSessionHasErrors('excluded');
});

test('reconciling an excluded transaction includes it again', function () {
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'is_excluded' => true, 'net' => 10]);

    $this->actingAs(User::factory()->create())
        ->post("/transactions/{$t->id}/reconcile", ['date' => '2026-11-01', 'net' => '10', 'wallet_id' => $wallet->id])
        ->assertRedirect();

    expect($t->refresh()->is_excluded)->toBeFalse();
    expect($t->is_reconciled)->toBeTrue();
});

test('excluding a transaction removes the tax payment it caused', function () {
    Wallet::factory()->create();
    $t = Transaction::factory()->create(['type' => 'income', 'invoice_date' => '2026-12-10', 'date' => '2026-12-10', 'net' => 1000, 'vat_amount' => 240]);
    // Its VAT payment, and the income tax estimated on it.
    $keys = array_keys(taxPayments());
    expect(array_filter($keys, fn ($k) => str_starts_with($k, 'tax:vat:')))->toHaveCount(1);
    expect(array_filter($keys, fn ($k) => str_starts_with($k, 'tax:income:')))->toHaveCount(8);

    $this->actingAs(User::factory()->create())->patch("/transactions/{$t->id}/excluded", ['excluded' => true]);

    expect(taxPayments())->toBe([]);
});

/** A monthly recurrence with one open-ended period of a plain amount. */
function exclusionRecurrence(): Recurrence
{
    $recurrence = Recurrence::factory()->create([
        'type' => 'expense',
        'entity_id' => Entity::factory()->create(['type' => 'supplier'])->id,
        'category_id' => Category::factory()->create(['type' => 'expense'])->id,
        'interval_unit' => 'month',
        'interval_count' => 1,
        'day_of_month' => 5,
        'start_date' => '2026-10-01',
        'end_date' => '2027-01-31',
    ]);
    $entry = RecurrenceEntry::factory()->create([
        'recurrence_id' => $recurrence->id,
        'start_date' => '2026-10-01',
        'end_date' => '2027-01-31',
        'amount_mode' => 'net',
    ]);
    $entry->lines()->create(['amount' => 100, 'vat_rate_id' => null, 'withheld_rate_id' => null, 'position' => 0]);

    return $recurrence->fresh();
}

test('excluding a recurrence switches off its unreconciled rows only, and new rows follow', function () {
    $recurrence = exclusionRecurrence();
    materialize($recurrence);
    $rows = Transaction::query()->where('recurrence_id', $recurrence->id)->orderBy('date')->get();
    expect($rows)->toHaveCount(4);
    $rows[0]->update(['is_reconciled' => true]);

    $this->actingAs(User::factory()->create())
        ->patch("/recurrences/{$recurrence->id}/excluded", ['excluded' => true])
        ->assertRedirect();

    $rows = Transaction::query()->where('recurrence_id', $recurrence->id)->orderBy('date')->get();
    expect($rows[0]->is_excluded)->toBeFalse();
    expect($rows->slice(1)->every(fn (Transaction $t) => $t->is_excluded))->toBeTrue();

    // A row generated later starts switched off too.
    $rows[3]->forceDelete();
    materialize($recurrence->fresh());
    expect(Transaction::query()->where('recurrence_id', $recurrence->id)->orderByDesc('date')->first()->is_excluded)->toBeTrue();
});

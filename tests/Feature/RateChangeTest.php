<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\RatePeriod;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

test('a dated change is added to a rate and must be unique per date', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/configuration/vat-rates/{$vat->id}/changes", ['valid_from' => '2027-07-01', 'rate' => '25'])
        ->assertRedirect();
    expect(RatePeriod::sole()->rate)->toBe('25.00');

    $this->actingAs($user)
        ->post("/configuration/vat-rates/{$vat->id}/changes", ['valid_from' => '2027-07-01', 'rate' => '26'])
        ->assertSessionHasErrors('valid_from');
});

test('a rate change recalculates unreconciled rows from its date and leaves the rest', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();
    $post = fn (string $date) => $this->actingAs($user)->post('/transactions', [
        'type' => 'income', 'date' => $date, 'invoice_date' => $date, 'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '1000', 'vat_rate_id' => $vat->id]],
    ]);
    $post('2027-06-15');
    $post('2027-07-15');
    $post('2027-08-15');
    [$before, $after, $reconciled] = Transaction::query()->where('type', 'income')->orderBy('date')->get()->all();
    $reconciled->update(['is_reconciled' => true]);

    $this->actingAs($user)->post("/configuration/vat-rates/{$vat->id}/changes", ['valid_from' => '2027-07-01', 'rate' => '25']);

    expect($before->refresh()->vat_amount)->toBe('240.00');
    expect($after->refresh()->vat_amount)->toBe('250.00');
    expect($after->vatLines()->sole()->vat_amount)->toBe('250.00');
    // Reconciled rows are frozen.
    expect($reconciled->refresh()->vat_amount)->toBe('240.00');
});

test('a recurrence generates each occurrence with the rate in force on its date', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    RatePeriod::create(['rate_type' => 'vat', 'rate_id' => $vat->id, 'valid_from' => '2027-01-01', 'rate' => 25]);
    $recurrence = Recurrence::factory()->create([
        'type' => 'expense',
        'entity_id' => Entity::factory()->create(['type' => 'supplier'])->id,
        'category_id' => Category::factory()->create(['type' => 'expense'])->id,
        'interval_unit' => 'month', 'interval_count' => 1, 'day_of_month' => 10,
        'start_date' => '2026-12-01', 'end_date' => '2027-01-31',
    ]);
    $entry = RecurrenceEntry::factory()->create(['recurrence_id' => $recurrence->id, 'start_date' => '2026-12-01', 'end_date' => '2027-01-31', 'amount_mode' => 'net']);
    $entry->lines()->create(['amount' => 100, 'vat_rate_id' => $vat->id, 'withheld_rate_id' => null, 'position' => 0]);

    materialize($recurrence->fresh());

    $rows = Transaction::query()->where('recurrence_id', $recurrence->id)->orderBy('date')->get();
    expect($rows->pluck('vat_amount')->all())->toBe(['24.00', '25.00']);
});

test('a change belongs to its own rate', function () {
    $a = VatRate::factory()->create();
    $b = VatRate::factory()->create();
    $change = RatePeriod::create(['rate_type' => 'vat', 'rate_id' => $a->id, 'valid_from' => '2027-01-01', 'rate' => 10]);

    $this->actingAs(User::factory()->create())
        ->delete("/configuration/vat-rates/{$b->id}/changes/{$change->id}")
        ->assertNotFound();
});

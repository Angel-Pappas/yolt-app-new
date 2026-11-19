<?php

use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\Plan;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-19');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A payment line payload. */
function paymentPayload(Entity $customer, Wallet $wallet, array $extra = []): array
{
    return array_merge([
        'entity_id' => $customer->id, 'wallet_id' => $wallet->id,
        'date' => '2026-11-25', 'invoice_date' => '2026-12-10',
        'amount' => '1000', 'amount_mode' => 'net', 'vat_rate_id' => null,
        'description' => 'Deposit',
    ], $extra);
}

test('a payment line plans the income with its VAT on its invoice date', function () {
    $customer = Entity::factory()->customer()->create();
    $wallet = Wallet::factory()->create();
    $vat = VatRate::factory()->create(['rate' => 24]);

    $this->actingAs(User::factory()->create())
        ->post('/payments', paymentPayload($customer, $wallet, ['vat_rate_id' => $vat->id]))
        ->assertRedirect();

    $line = PlannedTransaction::query()->sole();
    expect($line->type)->toBe('income');
    expect((float) $line->vat_amount)->toBe(240.0);
    expect($line->invoice_date->toDateString())->toBe('2026-12-10');
    $item = Plan::find($line->planKey());
    expect($item->kind)->toBe('payment');
    expect($item->open())->toBe(1240.0);
    // Its VAT is owed for December (the invoice month), paid at the end of January.
    expect(taxPayments())->toHaveKey('tax:vat:2026-12');
});

test('the total can be typed instead of the net', function () {
    $customer = Entity::factory()->customer()->create();
    $vat = VatRate::factory()->create(['rate' => 24]);

    $this->actingAs(User::factory()->create())->post('/payments', paymentPayload($customer, Wallet::factory()->create(), [
        'amount' => '1480', 'amount_mode' => 'total', 'vat_rate_id' => $vat->id,
    ]));

    $line = PlannedTransaction::query()->sole();
    expect((float) $line->net)->toBe(1193.55);
    expect((float) $line->net + (float) $line->vat_amount)->toBe(1480.0);
});

test('a payment line needs a customer', function () {
    $supplier = Entity::factory()->supplier()->create();

    $this->actingAs(User::factory()->create())
        ->post('/payments', paymentPayload($supplier, Wallet::factory()->create()))
        ->assertSessionHasErrors('entity_id');
});

test('a part-paid line keeps the rest open and cannot be deleted', function () {
    $customer = Entity::factory()->customer()->create();
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/payments', paymentPayload($customer, $wallet));
    $line = PlannedTransaction::query()->sole();
    Transaction::factory()->create([
        'type' => 'income', 'entity_id' => $customer->id, 'wallet_id' => $wallet->id, 'date' => '2026-11-20',
        'invoice_date' => '2026-12-10', 'net' => 400, 'vat_amount' => 0, 'withheld_amount' => 0,
        'is_reconciled' => true, 'managed_key' => $line->planKey(),
    ]);

    $item = Plan::find($line->planKey());
    expect($item->open())->toBe(600.0);
    expect($item->invoiceFixed())->toBeTrue();

    $this->actingAs($user)->delete("/payments/{$line->id}")->assertRedirect();
    expect(PlannedTransaction::query()->count())->toBe(1);
});

test('issued, switched off and deleted', function () {
    $customer = Entity::factory()->customer()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/payments', paymentPayload($customer, Wallet::factory()->create()));
    $line = PlannedTransaction::query()->sole();

    $this->actingAs($user)->patch("/payments/{$line->id}/issued", ['issued' => true]);
    expect($line->refresh()->is_issued)->toBeTrue();

    $this->actingAs($user)->patch("/payments/{$line->id}/excluded", ['excluded' => true]);
    expect(Plan::counted()->where('managed_key', $line->planKey()))->toHaveCount(0);

    $this->actingAs($user)->delete("/payments/{$line->id}")->assertRedirect();
    expect(PlannedTransaction::query()->count())->toBe(0);
});

test('the Income tab lists every line with what is paid and owed', function () {
    $customer = Entity::factory()->customer()->create(['name' => 'ACME']);
    $user = User::factory()->create();
    $this->actingAs($user)->post('/payments', paymentPayload($customer, Wallet::factory()->create()));

    $this->actingAs($user)->get('/planning/income')
        ->assertInertia(fn ($page) => $page->component('income/index')
            ->has('payments', 1)
            ->where('payments.0.entity', 'ACME')
            ->where('payments.0.open', 1000)
            ->where('payments.0.done', false));
});

test('recurring income creates monthly lines and rebuilds only untouched ones', function () {
    $customer = Entity::factory()->customer()->create();
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/recurring-incomes', [
        'entity_id' => $customer->id, 'wallet_id' => $wallet->id, 'net' => '500',
        'day_of_month' => 31, 'start_date' => '2026-11-01', 'end_date' => '2027-02-28',
        'description' => 'Maintenance',
    ])->assertRedirect();

    $lines = PlannedTransaction::query()->orderBy('date')->get();
    expect($lines->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-30', '2026-12-31', '2027-01-31', '2027-02-28']);

    // Mark December issued, then raise the amount: December stays as it was.
    $lines[1]->forceFill(['is_issued' => true])->save();
    $income = RecurringIncome::query()->sole();
    $this->actingAs($user)->patch("/recurring-incomes/{$income->id}", [
        'entity_id' => $customer->id, 'wallet_id' => $wallet->id, 'net' => '600',
        'day_of_month' => 31, 'start_date' => '2026-11-01', 'end_date' => '2027-02-28',
    ])->assertRedirect();

    $nets = PlannedTransaction::query()->orderBy('date')->pluck('net')->map(fn ($n) => (float) $n)->all();
    expect($nets)->toBe([600.0, 500.0, 600.0, 600.0]);

    // Deleting it removes its untouched lines only.
    $this->actingAs($user)->delete("/recurring-incomes/{$income->id}")->assertRedirect();
    expect(PlannedTransaction::query()->count())->toBe(1);
});

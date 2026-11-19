<?php

use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Plan;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-20');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A minimal one-off payload (the transaction form's shape). */
function oneOffPayload(Wallet $wallet, array $extra = []): array
{
    return array_merge([
        'type' => 'expense',
        'date' => '2026-10-01',
        'invoice_date' => '2026-10-01',
        'description' => 'New laptop',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '1200']],
    ], $extra);
}

test('a one-off is planned, not a transaction', function () {
    $wallet = Wallet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/one-offs', oneOffPayload($wallet))
        ->assertRedirect();

    $oneOff = PlannedTransaction::query()->sole();
    expect($oneOff->is_one_off)->toBeTrue();
    expect($oneOff->type)->toBe('expense');
    expect(Transaction::query()->count())->toBe(0);
    expect(Plan::find($oneOff->planKey())->open())->toBe(1200.0);
});

test('a one-off can be edited, switched off and deleted', function () {
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/one-offs', oneOffPayload($wallet));
    $oneOff = PlannedTransaction::query()->sole();

    $this->actingAs($user)->patch("/one-offs/{$oneOff->id}", oneOffPayload($wallet, ['lines' => [['amount' => '900']]]))->assertRedirect();
    expect((float) $oneOff->refresh()->net)->toBe(900.0);

    $this->actingAs($user)->patch("/one-offs/{$oneOff->id}/excluded", ['excluded' => true])->assertRedirect();
    expect($oneOff->refresh()->is_excluded)->toBeTrue();
    expect(Plan::counted()->where('managed_key', $oneOff->planKey()))->toHaveCount(0);

    $this->actingAs($user)->delete("/one-offs/{$oneOff->id}")->assertRedirect();
    expect(PlannedTransaction::query()->count())->toBe(0);
});

test('a real transaction that pays a one-off makes it done', function () {
    $wallet = Wallet::factory()->create();
    $supplier = Entity::factory()->supplier()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/one-offs', oneOffPayload($wallet, ['entity_id' => $supplier->id]));
    $oneOff = PlannedTransaction::query()->sole();

    $this->actingAs($user)->post('/transactions', oneOffPayload($wallet, [
        'entity_id' => $supplier->id, 'managed_key' => $oneOff->planKey(),
    ]))->assertRedirect();

    expect(Plan::find($oneOff->planKey())->isDone())->toBeTrue();
    $this->actingAs($user)->get('/planning/one-offs')
        ->assertInertia(fn ($page) => $page->component('one-offs/index')
            ->where('transactions.0.done', true)
            ->where('transactions.0.open', 0));
});

test('a one-off route never reaches a real transaction', function () {
    $real = Transaction::factory()->create(['is_one_off' => true]);

    $this->actingAs(User::factory()->create())->delete("/one-offs/{$real->id}")->assertNotFound();
    expect(Transaction::find($real->id))->not->toBeNull();
});

test('a guest cannot see the One-offs page', function () {
    $this->get('/planning/one-offs')->assertRedirect('/login');
});

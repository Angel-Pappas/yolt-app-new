<?php

use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

/** A minimal income payload for POST/PATCH /transactions. */
function incomePayload(Wallet $wallet, array $extra = []): array
{
    return array_merge([
        'type' => 'income',
        'date' => '2026-11-10',
        'invoice_date' => '2026-11-10',
        'description' => 'Website',
        'wallet_id' => $wallet->id,
        'amount_mode' => 'net',
        'lines' => [['amount' => '1000']],
    ], $extra);
}

test('a new income starts as agreed; an expense has no status', function () {
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', incomePayload($wallet))->assertRedirect();
    expect(Transaction::sole()->income_status)->toBe('agreed');

    $this->actingAs($user)->post('/transactions', incomePayload($wallet, ['type' => 'expense']));
    expect(Transaction::query()->where('type', 'expense')->sole()->income_status)->toBeNull();
});

test('an income status is set by hand and never touches reconciled', function () {
    $t = Transaction::factory()->create(['type' => 'income', 'income_status' => 'agreed', 'is_reconciled' => false]);

    $this->actingAs(User::factory()->create())
        ->patch("/transactions/{$t->id}/status", ['status' => 'paid'])
        ->assertRedirect();

    expect($t->refresh()->income_status)->toBe('paid');
    expect($t->is_reconciled)->toBeFalse();
});

test('reconciling never touches the status', function () {
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['type' => 'income', 'wallet_id' => $wallet->id, 'income_status' => 'invoiced', 'net' => 10]);

    $this->actingAs(User::factory()->create())
        ->post("/transactions/{$t->id}/reconcile", ['date' => '2026-11-01', 'net' => '10', 'wallet_id' => $wallet->id]);

    expect($t->refresh()->is_reconciled)->toBeTrue();
    expect($t->income_status)->toBe('invoiced');
});

test('an unknown status is rejected and an expense cannot take one', function () {
    $income = Transaction::factory()->create(['type' => 'income', 'income_status' => 'agreed']);
    $expense = Transaction::factory()->create(['type' => 'expense']);
    $user = User::factory()->create();

    $this->actingAs($user)->patch("/transactions/{$income->id}/status", ['status' => 'maybe'])->assertSessionHasErrors('status');
    $this->actingAs($user)->patch("/transactions/{$expense->id}/status", ['status' => 'paid'])->assertStatus(422);
});

test('editing an income keeps its status and project unless they are sent', function () {
    $wallet = Wallet::factory()->create();
    $project = Project::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post('/transactions', incomePayload($wallet, ['project_id' => $project->id, 'income_status' => 'invoiced']));
    $t = Transaction::sole();
    expect($t->project_id)->toBe($project->id);

    $this->actingAs($user)->patch("/transactions/{$t->id}", incomePayload($wallet, ['description' => 'Site']));

    expect($t->refresh()->income_status)->toBe('invoiced');
    expect($t->project_id)->toBe($project->id);
});

test('turning an income into an expense clears its status and project', function () {
    $wallet = Wallet::factory()->create();
    $t = Transaction::factory()->create(['type' => 'income', 'income_status' => 'paid', 'project_id' => Project::factory()->create()->id, 'wallet_id' => $wallet->id]);

    $this->actingAs(User::factory()->create())->patch("/transactions/{$t->id}", incomePayload($wallet, ['type' => 'expense']));

    expect($t->refresh()->income_status)->toBeNull();
    expect($t->project_id)->toBeNull();
});

test('the invoice date follows the date on reconcile until it was set apart by hand', function () {
    $wallet = Wallet::factory()->create();
    $follows = Transaction::factory()->create(['type' => 'income', 'wallet_id' => $wallet->id, 'date' => '2026-11-10', 'invoice_date' => '2026-11-10', 'net' => 10]);
    $apart = Transaction::factory()->create(['type' => 'income', 'wallet_id' => $wallet->id, 'date' => '2026-11-10', 'invoice_date' => '2026-05-03', 'net' => 10]);
    $user = User::factory()->create();

    foreach ([$follows, $apart] as $t) {
        $this->actingAs($user)->post("/transactions/{$t->id}/reconcile", ['date' => '2026-12-02', 'net' => '10', 'wallet_id' => $wallet->id]);
    }

    expect($follows->refresh()->invoice_date->toDateString())->toBe('2026-12-02');
    expect($apart->refresh()->invoice_date->toDateString())->toBe('2026-05-03');
});

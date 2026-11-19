<?php

use App\Models\Entity;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\ProjectOverview;

test('a project is created for a customer and opens its own page', function () {
    $customer = Entity::factory()->create(['type' => 'customer']);

    $this->actingAs(User::factory()->create())
        ->post('/projects', ['name' => 'Website', 'entity_id' => $customer->id, 'agreed_total' => '30000'])
        ->assertRedirect('/projects/'.Project::sole()->id);

    expect(Project::sole()->agreed_total)->toBe('30000.00');
});

test('a project needs a name and a customer', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['name' => '', 'entity_id' => null])
        ->assertSessionHasErrors(['name', 'entity_id']);
});

test('the summary comes from the installments', function () {
    $project = Project::factory()->create(['agreed_total' => 30000]);
    $make = fn (string $status, string $date, float $net) => Transaction::factory()->create([
        'type' => 'income', 'project_id' => $project->id, 'income_status' => $status, 'date' => $date, 'net' => $net,
    ]);
    $make('paid', '2026-10-01', 10000);
    $make('invoiced', '2026-11-01', 10000);
    $make('agreed', '2026-12-01', 5000);
    $make('dropped', '2026-12-15', 4000);

    $summary = ProjectOverview::summarize($project, $project->transactions()->get());

    expect($summary)->toMatchArray([
        'installments' => 4,
        'scheduled' => 25000.0,
        'invoiced' => 20000.0,
        'paid' => 10000.0,
        'outstanding' => 15000.0,
        'unscheduled' => 5000.0,
        'next_due' => '2026-11-01',
        'done' => false,
    ]);
});

test('a project is done when every installment is paid or dropped', function () {
    $project = Project::factory()->create();
    Transaction::factory()->create(['type' => 'income', 'project_id' => $project->id, 'income_status' => 'paid']);
    Transaction::factory()->create(['type' => 'income', 'project_id' => $project->id, 'income_status' => 'dropped']);

    expect(ProjectOverview::summarize($project, $project->transactions()->get())['done'])->toBeTrue();
    expect(ProjectOverview::summarize($project, collect())['done'])->toBeFalse();
});

test('excluding a project switches off its unreconciled installments, and new ones follow', function () {
    $wallet = Wallet::factory()->create();
    $project = Project::factory()->create();
    $open = Transaction::factory()->create(['type' => 'income', 'project_id' => $project->id]);
    $done = Transaction::factory()->create(['type' => 'income', 'project_id' => $project->id, 'is_reconciled' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->patch("/projects/{$project->id}/excluded", ['excluded' => true])->assertRedirect();

    expect($open->refresh()->is_excluded)->toBeTrue();
    expect($done->refresh()->is_excluded)->toBeFalse();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income', 'date' => '2026-11-10', 'invoice_date' => '2026-11-10', 'wallet_id' => $wallet->id,
        'amount_mode' => 'net', 'lines' => [['amount' => '100']], 'project_id' => $project->id,
    ]);
    expect(Transaction::query()->latest('id')->first()->is_excluded)->toBeTrue();
});

test('deleting a project keeps its installments', function () {
    $project = Project::factory()->create();
    $t = Transaction::factory()->create(['type' => 'income', 'project_id' => $project->id]);

    $this->actingAs(User::factory()->create())->delete("/projects/{$project->id}")->assertRedirect('/projects');

    expect($t->refresh()->project_id)->toBeNull();
    expect(Project::count())->toBe(0);
});

test('guests cannot reach projects', function () {
    $this->get('/projects')->assertRedirect('/login');
});

<?php

use App\Models\BankLine;
use App\Models\BankStatement;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletConclusion;

test('the cutover moves live data onto the plan without losing anything real', function () {
    $wallet = Wallet::factory()->create();
    $row = fn (array $extra) => Transaction::factory()->create(array_merge([
        'type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-10', 'invoice_date' => '2026-10-10',
        'net' => 100, 'vat_amount' => 0, 'withheld_amount' => 0,
    ], $extra));

    $generatedOpen = $row(['source' => 'recurrence', 'managed_key' => 'recurrence:1:2026-12-01', 'is_reconciled' => false]);
    $taxOpen = $row(['source' => 'tax', 'managed_key' => 'tax:vat:2026-11', 'is_reconciled' => false]);
    $generatedPaid = $row(['source' => 'recurrence', 'managed_key' => 'recurrence:1:2026-10-01', 'is_reconciled' => true]);
    $handTyped = $row(['is_reconciled' => false]);
    $oneOffOpen = $row(['is_one_off' => true, 'is_reconciled' => false, 'date' => '2026-12-20']);
    $oneOffPaid = $row(['is_one_off' => true, 'is_reconciled' => true]);
    $project = Project::factory()->create(['entity_id' => Entity::factory()->customer()->create()->id, 'agreed_total' => 5000]);

    $line = fn (string $date, int $n) => BankLine::query()->create([
        'wallet_id' => $wallet->id, 'date' => $date, 'position' => $n, 'text' => "L{$n}", 'amount' => -10,
        'reference' => '', 'fingerprint' => "f{$n}",
    ]);
    $paired = $line('2026-10-01', 1);
    $row(['bank_line_id' => $paired->id, 'is_reconciled' => true]);
    $waiting = $line('2026-10-15', 2);
    $later = $line('2026-10-20', 3);

    (require database_path('migrations/2026_11_20_000002_move_to_the_plan.php'))->up();

    // Unconfirmed generated rows are soft-deleted; everything real stays.
    expect(Transaction::query()->find($generatedOpen->id))->toBeNull();
    expect(Transaction::withTrashed()->find($generatedOpen->id))->not->toBeNull();
    expect(Transaction::query()->find($taxOpen->id))->toBeNull();
    expect(Transaction::query()->find($generatedPaid->id))->not->toBeNull();
    expect(Transaction::query()->find($handTyped->id))->not->toBeNull();
    expect(Transaction::query()->find($oneOffPaid->id))->not->toBeNull();
    // The open one-off is planned now.
    expect(Transaction::query()->find($oneOffOpen->id))->toBeNull();
    expect(PlannedTransaction::query()->find($oneOffOpen->id))->not->toBeNull();
    // The agreed total is the project's deliverable.
    expect($project->deliverables()->sole()->net)->toBe('5000.00');
    // Bank lines: concluded up to the day before the first waiting line, the rest open.
    $concluded = BankStatement::query()->where('status', 'concluded')->sole();
    $open = BankStatement::query()->where('status', 'open')->sole();
    expect($paired->refresh()->bank_statement_id)->toBe($concluded->id);
    expect($waiting->refresh()->bank_statement_id)->toBe($open->id);
    expect($later->refresh()->bank_statement_id)->toBe($open->id);
    expect($wallet->refresh()->concluded_until->toDateString())->toBe('2026-10-14');
    expect(WalletConclusion::query()->sole()->until->toDateString())->toBe('2026-10-14');
});

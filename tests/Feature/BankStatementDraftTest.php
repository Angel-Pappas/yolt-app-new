<?php

use App\Models\BankLine;
use App\Models\BankStatement;
use App\Models\Entity;
use App\Models\PlanAdjustment;
use App\Models\PlannedTransaction;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WalletConclusion;
use App\Support\Plan;
use App\Support\StatementDraft;
use App\Support\VatLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16');
    $this->user = User::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/** An Alpha CSV (see BankImportTest::alphaCsv) as an upload. */
function statementFile(array $lines, string $balance = '2915,70 Π'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('alpha.csv', alphaCsv($lines, $balance));
}

/** An open statement of a wallet with lines [date, amount, text]. */
function openStatement(Wallet $wallet, array $lines, ?float $closing = null): BankStatement
{
    $statement = new BankStatement([
        'wallet_id' => $wallet->id,
        'from_date' => min(array_column($lines, 0)),
        'to_date' => max(array_column($lines, 0)),
        'closing_balance' => $closing,
    ]);
    $statement->save();
    foreach ($lines as $i => [$date, $amount, $text]) {
        bankLine($wallet, $date, $amount, $text, $i + 1)->forceFill(['bank_statement_id' => $statement->id])->save();
    }

    return $statement;
}

function customerLine(Wallet $wallet, Entity $customer, string $date, string $invoice, float $net, ?int $vat = null): PlannedTransaction
{
    $line = PlannedTransaction::query()->create([
        'type' => 'income', 'date' => $date, 'invoice_date' => $invoice, 'description' => "Line {$date}",
        'net' => $net, 'vat_amount' => $vat ? round($net * 0.24, 2) : 0, 'withheld_amount' => 0, 'vat_rate_id' => $vat,
        'entity_id' => $customer->id, 'wallet_id' => $wallet->id,
    ]);
    if ($vat) {
        $line->vatLines()->create(['net' => $net, 'vat_rate_id' => $vat, 'vat_amount' => round($net * 0.24, 2), 'position' => 0]);
    }

    return $line;
}

test('an upload opens a draft: sure matches are decided, nothing is written', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-12', 'invoice_date' => '2026-11-12', 'net' => 124, 'vat_amount' => 0, 'withheld_amount' => 0]);

    $this->actingAs($this->user)->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => statementFile([
        [1, '12/11/2026', 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ', 'R2', '4,00', 'Χ'],
        [2, '12/11/2026', 'FΑCΕΒΚ', 'R1', '124,00', 'Χ'],
        [3, '13/11/2026', 'UNKNOWN', 'R3', '50,00', 'Π'],
    ])])->assertRedirect();

    $statement = BankStatement::query()->sole();
    expect($statement->status)->toBe('open');
    expect($statement->closing_balance)->toBe('2915.70');
    expect($statement->lines()->count())->toBe(3);
    // The row and the fee are decided in the draft; nothing is paired or added yet.
    expect(count(StatementDraft::decisions($statement)))->toBe(2);
    expect($row->refresh()->bank_line_id)->toBeNull();
    expect(Transaction::query()->count())->toBe(1);

    // One open statement per wallet.
    $this->actingAs($this->user)->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => statementFile([[1, '14/11/2026', 'X', 'R9', '1,00', 'Χ']])])
        ->assertSessionHasErrors('file');
});

test('concluding applies every decision at once and moves the concluded date', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-10', 'invoice_date' => '2026-11-10', 'net' => 124, 'vat_amount' => 0, 'withheld_amount' => 0]);
    $statement = openStatement($wallet, [['2026-11-12', -124, 'FΑCΕΒΚ'], ['2026-11-12', -4, 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ']]);
    [$payment, $fee] = $statement->lines()->orderBy('position')->get()->all();

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $payment->id, 't' => 'row', 'id' => $row->id]);
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $fee->id, 't' => 'fee']);
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude")->assertRedirect('/bank-import?tab=statements');

    expect($statement->refresh()->status)->toBe('concluded');
    $row->refresh();
    expect($row->bank_line_id)->toBe($payment->id);
    expect($row->is_reconciled)->toBeTrue();
    expect($row->date->toDateString())->toBe('2026-11-12');
    expect(Transaction::query()->where('bank_line_id', $fee->id)->sole()->entity_id)->toBe($wallet->fee_entity_id);
    expect($wallet->refresh()->concluded_until->toDateString())->toBe('2026-11-12');
    expect(WalletConclusion::query()->sole()->bank_statement_id)->toBe($statement->id);
});

test('conclude waits for every line, and clears a line whose row changed meanwhile', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-12', 'invoice_date' => '2026-11-12', 'net' => 10, 'vat_amount' => 0, 'withheld_amount' => 0]);
    $statement = openStatement($wallet, [['2026-11-12', -10, 'A'], ['2026-11-13', -20, 'B']]);
    $a = $statement->lines()->orderBy('position')->first();

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $a->id, 't' => 'row', 'id' => $row->id]);
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude")->assertSessionHasErrors('conclude');
    expect($statement->refresh()->status)->toBe('open');

    // Decide B too, then the row is edited elsewhere: A is cleared for a second look.
    $b = $statement->lines()->orderByDesc('position')->first();
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $b->id, 't' => 'fee']);
    Carbon::setTestNow('2026-11-16 12:00:05');
    $row->update(['description' => 'changed']);
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude")->assertSessionHasErrors('conclude');
    expect(StatementDraft::decisions($statement))->not->toHaveKey($a->id);
    expect($row->refresh()->bank_line_id)->toBeNull();
});

test('a balance off from the bank asks before concluding', function () {
    $wallet = feeWallet();
    $statement = openStatement($wallet, [['2026-11-12', -4, 'ΕΞΟΔΑ']], closing: 100.0);
    $line = $statement->lines()->sole();
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $line->id, 't' => 'fee']);

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude")->assertSessionHasErrors('difference');
    // Rolled back: nothing written.
    expect(Transaction::query()->count())->toBe(0);
    expect($statement->refresh()->status)->toBe('open');

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude", ['accept_difference' => true])->assertRedirect();
    expect($statement->refresh()->status)->toBe('concluded');
});

test('undo takes the last step back; a line can be cleared', function () {
    $wallet = feeWallet();
    $statement = openStatement($wallet, [['2026-11-12', -4, 'ΕΞΟΔΑ']]);
    $line = $statement->lines()->sole();

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $line->id, 't' => 'fee']);
    expect(StatementDraft::decisions($statement))->toHaveKey($line->id);
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/undo");
    expect(StatementDraft::decisions($statement))->toBe([]);

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/pieces", ['line_id' => $line->id, 't' => 'fee']);
    $this->actingAs($this->user)->delete("/bank-statements/{$statement->id}/lines/{$line->id}");
    expect(StatementDraft::decisions($statement))->toBe([]);
    // Undo brings the cleared decision back.
    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/undo");
    expect(StatementDraft::decisions($statement))->toHaveKey($line->id);
});

test('a row added from a bank line waits in the draft, with its bank fee', function () {
    $wallet = feeWallet();
    $supplier = Entity::factory()->supplier()->create();
    $statement = openStatement($wallet, [['2026-11-12', -104, 'PAYMENT']]);
    $line = $statement->lines()->sole();

    $this->actingAs($this->user)->post('/transactions', [
        'type' => 'expense', 'date' => '2026-11-12', 'invoice_date' => '2026-11-12', 'wallet_id' => $wallet->id,
        'entity_id' => $supplier->id, 'amount_mode' => 'net', 'lines' => [['amount' => '100']],
        'bank_line_id' => $line->id, 'bank_fee' => '4',
    ])->assertSessionHasNoErrors();
    expect(Transaction::query()->count())->toBe(0);
    expect(StatementDraft::covered(StatementDraft::decisions($statement)[$line->id]))->toBe(104.0);

    $this->actingAs($this->user)->post("/bank-statements/{$statement->id}/conclude")->assertRedirect();
    $rows = Transaction::query()->where('bank_line_id', $line->id)->orderBy('id')->get();
    expect($rows->pluck('entity_id')->all())->toBe([$supplier->id, $wallet->fee_entity_id]);
    expect($rows->every->is_reconciled)->toBeTrue();
});

test('Angel\'s 10k example: paid in parts, the rest carried, VAT in the invoice month', function () {
    $wallet = feeWallet();
    $customer = Entity::factory()->customer()->create(['name' => 'Big Customer']);
    $vat = VatRate::factory()->create(['rate' => 24])->id;
    // Two lines of 5k + VAT each, both invoiced in December.
    $a = customerLine($wallet, $customer, '2026-11-15', '2026-12-15', 5000, $vat);
    $b = customerLine($wallet, $customer, '2026-12-15', '2026-12-15', 5000, $vat);

    // November: the customer pays 5.000 of line A's 6.200; the rest is due in December.
    $nov = openStatement($wallet, [['2026-11-16', 5000, 'BIG CUSTOMER']]);
    $this->actingAs($this->user)->post("/bank-statements/{$nov->id}/pieces", [
        'line_id' => $nov->lines()->sole()->id, 't' => 'plan', 'key' => $a->planKey(),
    ])->assertSessionHasErrors('rest');
    $this->actingAs($this->user)->post("/bank-statements/{$nov->id}/pieces", [
        'line_id' => $nov->lines()->sole()->id, 't' => 'plan', 'key' => $a->planKey(), 'rest' => '2026-12-15',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post("/bank-statements/{$nov->id}/conclude")->assertRedirect();

    $itemA = Plan::find($a->planKey());
    expect($itemA->open())->toBe(1200.0);
    expect($itemA->date()->toDateString())->toBe('2026-12-15');
    expect($a->refresh()->invoice_pinned)->toBeTrue();
    expect($a->is_issued)->toBeFalse();
    $paid = Transaction::query()->where('managed_key', $a->planKey())->sole();
    expect($paid->invoice_date->toDateString())->toBe('2026-12-15');

    // December: one 7.400 payment covers A's 1.200 and B's 6.200.
    Carbon::setTestNow('2026-12-20');
    $dec = openStatement($wallet, [['2026-12-18', 7400, 'BIG CUSTOMER']]);
    $line = $dec->lines()->sole();
    $this->actingAs($this->user)->post("/bank-statements/{$dec->id}/pieces", ['line_id' => $line->id, 't' => 'plan', 'key' => $a->planKey()])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post("/bank-statements/{$dec->id}/pieces", ['line_id' => $line->id, 't' => 'plan', 'key' => $b->planKey()])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post("/bank-statements/{$dec->id}/conclude")->assertRedirect();

    Plan::flush();
    expect(Plan::find($a->planKey())->isDone())->toBeTrue();
    expect(Plan::find($b->planKey())->isDone())->toBeTrue();
    expect($a->refresh()->is_issued)->toBeTrue();
    // Everything paid adds up to 10.000 + 2.400 VAT, all of it December's VAT.
    $real = Transaction::query()->where('entity_id', $customer->id)->get();
    expect(round($real->sum(fn ($t) => (float) $t->net), 2))->toBe(10000.0);
    expect(round($real->sum(fn ($t) => (float) $t->vat_amount), 2))->toBe(2400.0);
    $december = collect(VatLedger::monthly())->firstWhere('month', '2026-12');
    expect((float) $december['income_vat'])->toBe(2400.0);
});

test('a planned item is suggested for a line with its open amount; of two alike, the oldest', function () {
    $wallet = feeWallet();
    $customer = Entity::factory()->customer()->create();
    customerLine($wallet, $customer, '2026-11-20', '2026-11-20', 300);
    $older = customerLine($wallet, $customer, '2026-10-05', '2026-10-05', 300);
    customerLine($wallet, $customer, '2026-12-30', '2026-12-30', 300); // too far ahead

    $statement = openStatement($wallet, [['2026-11-14', 300, 'TRANSFER']]);
    $this->actingAs($this->user)->get("/bank-import?tab=work&statement={$statement->id}")
        ->assertInertia(fn ($page) => $page->component('bank-import/index')
            ->where('statement.rows.0.proposal.kind', 'plan')
            ->where('statement.rows.0.proposal.plan.key', $older->planKey())
            ->has('statement.rows.0.plan_options', 2));
});

test('a discarded statement takes its lines with it', function () {
    $wallet = feeWallet();
    $statement = openStatement($wallet, [['2026-11-12', -4, 'ΕΞΟΔΑ']]);

    $this->actingAs($this->user)->delete("/bank-statements/{$statement->id}")->assertRedirect();

    expect(BankStatement::query()->count())->toBe(0);
    expect(BankLine::query()->count())->toBe(0);
});

test('a wallet without statements is concluded by hand, and its unpaid plan shows as not matched', function () {
    $cash = Wallet::factory()->create(['name' => 'Cash']);
    $oneOff = PlannedTransaction::query()->create([
        'type' => 'expense', 'date' => '2026-10-20', 'invoice_date' => '2026-10-20', 'description' => 'Chairs',
        'net' => 300, 'vat_amount' => 0, 'withheld_amount' => 0, 'wallet_id' => $cash->id, 'is_one_off' => true,
        'entity_id' => Entity::factory()->supplier()->create()->id,
    ]);

    $this->actingAs($this->user)->post("/wallets/{$cash->id}/conclude", ['until' => '2026-10-31'])->assertRedirect();

    expect($cash->refresh()->concluded_until->toDateString())->toBe('2026-10-31');
    expect(Plan::notMatched()->keys()->all())->toBe([$oneOff->planKey()]);
    $this->actingAs($this->user)->get('/bank-import?tab=not-matched')
        ->assertInertia(fn ($page) => $page->has('notMatched', 1)->where('notMatched.0.description', 'Chairs'));
});

test('a not-matched item can be linked, moved or cancelled', function () {
    $cash = Wallet::factory()->create(['concluded_until' => '2026-10-31']);
    $supplier = Entity::factory()->supplier()->create();
    $make = fn (string $what) => PlannedTransaction::query()->create([
        'type' => 'expense', 'date' => '2026-10-20', 'invoice_date' => '2026-10-20', 'description' => $what,
        'net' => 300, 'vat_amount' => 0, 'withheld_amount' => 0, 'wallet_id' => $cash->id, 'is_one_off' => true,
        'entity_id' => $supplier->id,
    ]);
    [$linked, $moved, $cancelled] = [$make('A'), $make('B'), $make('C')];
    $real = Transaction::factory()->create(['type' => 'expense', 'entity_id' => $supplier->id, 'wallet_id' => $cash->id,
        'date' => '2026-10-22', 'invoice_date' => '2026-10-22', 'net' => 300, 'vat_amount' => 0, 'withheld_amount' => 0]);

    $this->actingAs($this->user)->post('/plan-items/link', ['key' => $linked->planKey(), 'transaction_id' => $real->id])->assertRedirect();
    $this->actingAs($this->user)->post('/plan-items/move', ['key' => $moved->planKey(), 'date' => '2026-12-01'])->assertRedirect();
    $this->actingAs($this->user)->post('/plan-items/cancel', ['key' => $cancelled->planKey()])->assertRedirect();

    expect($real->refresh()->managed_key)->toBe($linked->planKey());
    expect($moved->refresh()->date->toDateString())->toBe('2026-12-01');
    expect(PlannedTransaction::query()->find($cancelled->id))->toBeNull();
    expect(Plan::notMatched())->toHaveCount(0);
});

test('a computed item is cancelled or moved by an adjustment', function () {
    $wallet = Wallet::factory()->create(['concluded_until' => '2026-10-31']);
    $entity = Entity::factory()->supplier()->create();
    $recurrence = Recurrence::factory()->create(['entity_id' => $entity->id, 'wallet_id' => $wallet->id,
        'interval_count' => 1, 'interval_unit' => 'month', 'day_of_month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
    $entry = RecurrenceEntry::factory()->create(['recurrence_id' => $recurrence->id, 'start_date' => '2026-10-01', 'end_date' => null, 'net' => 0]);
    RecurrenceEntryLine::factory()->create(['recurrence_entry_id' => $entry->id, 'amount' => 50, 'position' => 0]);
    $key = "recurrence:{$recurrence->id}:2026-10-10";

    $this->actingAs($this->user)->post('/plan-items/cancel', ['key' => $key])->assertRedirect();

    expect(PlanAdjustment::query()->where('plan_key', $key)->sole()->cancelled)->toBeTrue();
    expect(Plan::notMatched())->toHaveCount(0);
});

test('the Statements view lists uploads and hand conclusions', function () {
    $wallet = feeWallet();
    openStatement($wallet, [['2026-11-12', -4, 'ΕΞΟΔΑ']]);
    StatementDraft::concludeWallet(Wallet::factory()->create(['name' => 'Cash']), '2026-10-31', $this->user->id);

    $this->actingAs($this->user)->get('/bank-import?tab=statements')
        ->assertInertia(fn ($page) => $page->component('bank-import/index')
            ->where('tab', 'statements')
            ->has('history', 2)
            ->where('wallets.0.has_open', true));
});

test('guests cannot reach the bank import', function () {
    $this->get('/bank-import')->assertRedirect('/login');
});

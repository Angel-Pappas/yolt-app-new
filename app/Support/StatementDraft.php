<?php

namespace App\Support;

use App\Models\BankLine;
use App\Models\BankStatement;
use App\Models\BankStatementStep;
use App\Models\PlanAdjustment;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletConclusion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Working on an uploaded statement (Angel, 2026-11-19): every decision about its
 * lines is kept as a **draft** — one saved step per action, undoable — and nothing
 * in the books changes until the statement is **concluded**, which applies every
 * decision at once and moves the wallet's concluded date to the statement's end.
 *
 * A line's decision is the pieces that make it up, adding up to the line:
 *
 *  - `row`  — a transaction already in the app (whole);
 *  - `plan` — a planned item, in full or in part (`amount`; a part names the date the
 *             rest is now expected, `rest`);
 *  - `new`  — a transaction to add, from the add form (`data`, plus an optional bank
 *             fee paid with it, `fee`);
 *  - `like` — a transaction shaped like an earlier one (the whole line);
 *  - `fee`  — the bank's own charge (the whole line).
 *
 * Each piece remembers when it was decided (`at`), so concluding can tell whether
 * the row or item behind it changed meanwhile — then that line is cleared for a
 * second look instead of being applied blindly.
 *
 * Pieces are kept as JSON (maps of these keys), so they are read as plain arrays.
 */
final class StatementDraft
{
    /**
     * The decisions as they stand: line id → pieces.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public static function decisions(BankStatement $statement): array
    {
        $step = BankStatementStep::query()->where('bank_statement_id', $statement->id)->latest('id')->first();
        $decisions = $step === null ? [] : (array) $step->decisions;

        $out = [];
        foreach ($decisions as $lineId => $pieces) {
            $out[(int) $lineId] = array_values((array) $pieces);
        }

        return $out;
    }

    /** The label of the step Undo would take back (null when there is none). */
    public static function lastStep(BankStatement $statement): ?string
    {
        return BankStatementStep::query()->where('bank_statement_id', $statement->id)->latest('id')->value('label');
    }

    /**
     * Save a new step: the whole set of decisions after an action.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $decisions
     */
    public static function record(BankStatement $statement, string $label, array $decisions, ?int $userId): void
    {
        $decisions = array_map('array_values', array_filter($decisions, fn (array $pieces): bool => $pieces !== []));
        BankStatementStep::query()->create([
            'bank_statement_id' => $statement->id,
            'user_id' => $userId,
            'label' => $label,
            'decisions' => (object) $decisions,
        ]);
    }

    /**
     * Add a piece to a line. A line already covered starts over with this piece
     * (choosing again replaces the decision); else the piece joins the others.
     *
     * @param  array<string, mixed>  $piece
     */
    public static function addPiece(BankStatement $statement, BankLine $line, array $piece, string $label, ?int $userId): void
    {
        $decisions = self::decisions($statement);
        $pieces = $decisions[$line->id] ?? [];
        $whole = in_array($piece['t'], ['fee', 'like'], true);
        if ($whole || self::covered($pieces) >= abs((float) $line->amount) - 0.005) {
            $pieces = [];
        }
        $pieces[] = [...$piece, 'at' => Carbon::now()->toIso8601String()];
        $decisions[$line->id] = $pieces;
        self::record($statement, $label, $decisions, $userId);
    }

    /** Take a line's decision back (as a step of its own, so Undo restores it). */
    public static function clearLine(BankStatement $statement, BankLine $line, ?int $userId): void
    {
        $decisions = self::decisions($statement);
        unset($decisions[$line->id]);
        self::record($statement, __('Cleared a line'), $decisions, $userId);
    }

    /** Undo: drop the latest step. */
    public static function undo(BankStatement $statement): void
    {
        BankStatementStep::query()->where('bank_statement_id', $statement->id)->latest('id')->first()?->delete();
    }

    /**
     * What a line's pieces cover together.
     *
     * @param  list<array<string, mixed>>  $pieces
     */
    public static function covered(array $pieces): float
    {
        return round(array_sum(array_map(fn (array $p): float => (float) $p['amount'], $pieces)), 2);
    }

    /**
     * Rows the draft gives to lines (so they aren't suggested for another).
     *
     * @param  array<int, list<array<string, mixed>>>  $decisions
     * @return array<int, true>
     */
    public static function usedRows(array $decisions): array
    {
        $out = [];
        foreach ($decisions as $pieces) {
            foreach ($pieces as $p) {
                if ($p['t'] === 'row' && isset($p['id'])) {
                    $out[(int) $p['id']] = true;
                }
            }
        }

        return $out;
    }

    /**
     * How much of each planned item the draft already uses.
     *
     * @param  array<int, list<array<string, mixed>>>  $decisions
     * @return array<string, float>
     */
    public static function usedPlan(array $decisions): array
    {
        $out = [];
        foreach ($decisions as $pieces) {
            foreach ($pieces as $p) {
                if ($p['t'] === 'plan' && isset($p['key'])) {
                    $key = (string) $p['key'];
                    $out[$key] = round(($out[$key] ?? 0.0) + (float) $p['amount'], 2);
                }
            }
        }

        return $out;
    }

    /**
     * On upload: decide what needs no decision — a row with the line's very amount
     * on its very day (or rows on that day adding up to it), a planned item with the
     * line's amount on its day, and the bank's own charges — as the draft's first
     * step. Returns how many lines it decided.
     */
    public static function settle(BankStatement $statement, ?int $userId): int
    {
        $wallet = $statement->wallet;
        $decisions = self::decisions($statement);
        $lines = BankLine::query()->where('bank_statement_id', $statement->id)->pending()->get()
            ->reject(fn (BankLine $l): bool => isset($decisions[$l->id]));
        $suggestions = BankReconciler::suggestions($wallet, $lines, self::usedRows($decisions), self::usedPlan($decisions));
        $settled = 0;

        foreach ($lines as $line) {
            $s = $suggestions[$line->id] ?? null;
            $proposal = $s['proposal'] ?? null;
            $amount = abs((float) $line->amount);
            $at = Carbon::now()->toIso8601String();
            $pieces = match ($proposal['kind'] ?? null) {
                'fee' => [['t' => 'fee', 'amount' => $amount, 'at' => $at]],
                'match' => collect($s['candidates'])->contains(fn (array $c): bool => $c['transaction']->id === $proposal['transaction_id']
                    && $c['exact'] && $c['transaction']->date->isSameDay($line->date))
                    ? [['t' => 'row', 'id' => $proposal['transaction_id'], 'amount' => $amount, 'at' => $at]]
                    : null,
                'group' => Transaction::query()->findMany($proposal['transaction_ids'])
                    ->every(fn (Transaction $t): bool => $t->date->isSameDay($line->date))
                    ? Transaction::query()->findMany($proposal['transaction_ids'])
                        ->map(fn (Transaction $t): array => ['t' => 'row', 'id' => $t->id, 'amount' => BankReconciler::cash($t), 'at' => $at])->values()->all()
                    : null,
                'plan' => Plan::find($proposal['key'])?->date()->isSameDay($line->date)
                    ? [['t' => 'plan', 'key' => $proposal['key'], 'amount' => $amount, 'rest' => null, 'at' => $at]]
                    : null,
                default => null,
            };
            if ($pieces !== null) {
                $decisions[$line->id] = $pieces;
                $settled++;
            }
        }

        if ($settled > 0) {
            self::record($statement, __('Sure matches'), $decisions, $userId);
        }

        return $settled;
    }

    /**
     * Apply what a line's suggestion says, for the chosen lines (one step).
     *
     * @param  list<int>  $lineIds
     */
    public static function applySuggestions(BankStatement $statement, array $lineIds, ?int $userId): int
    {
        $decisions = self::decisions($statement);
        $lines = BankLine::query()->where('bank_statement_id', $statement->id)->pending()->get()
            ->reject(fn (BankLine $l): bool => isset($decisions[$l->id]));
        $suggestions = BankReconciler::suggestions($statement->wallet, $lines, self::usedRows($decisions), self::usedPlan($decisions));
        $at = Carbon::now()->toIso8601String();
        $count = 0;

        foreach ($lines->whereIn('id', $lineIds) as $line) {
            $proposal = $suggestions[$line->id]['proposal'] ?? null;
            $amount = abs((float) $line->amount);
            $pieces = match ($proposal['kind'] ?? null) {
                'match' => [['t' => 'row', 'id' => $proposal['transaction_id'], 'amount' => $amount, 'at' => $at]],
                'group' => Transaction::query()->findMany($proposal['transaction_ids'])
                    ->map(fn (Transaction $t): array => ['t' => 'row', 'id' => $t->id, 'amount' => BankReconciler::cash($t), 'at' => $at])->values()->all(),
                'plan' => [['t' => 'plan', 'key' => $proposal['key'], 'amount' => $amount, 'rest' => null, 'at' => $at]],
                'fee' => [['t' => 'fee', 'amount' => $amount, 'at' => $at]],
                'like' => [['t' => 'like', 'id' => $proposal['transaction_id'], 'amount' => $amount, 'at' => $at]],
                default => null,
            };
            if ($pieces !== null) {
                $decisions[$line->id] = $pieces;
                $count++;
            }
        }
        if ($count > 0) {
            self::record($statement, trans_choice('Accepted :count suggestion|Accepted :count suggestions', $count, ['count' => $count]), $decisions, $userId);
        }

        return $count;
    }

    /**
     * Conclude: check, then apply every decision at once and move the wallet's
     * concluded date. Returns what stopped it, if anything — lines still to decide,
     * decisions whose row or item changed meanwhile (cleared for a second look), or
     * a difference from the bank's closing balance not yet confirmed.
     *
     * @return array{done: bool, undecided: list<int>, changed: list<int>, difference: float|null}
     */
    public static function conclude(BankStatement $statement, ?int $userId, bool $acceptDifference): array
    {
        $decisions = self::decisions($statement);
        $lines = BankLine::query()->where('bank_statement_id', $statement->id)->pending()
            ->orderBy('date')->orderBy('position')->get();

        $undecided = array_values($lines->filter(fn (BankLine $l): bool => abs(self::covered($decisions[$l->id] ?? []) - abs((float) $l->amount)) >= 0.005)
            ->map(fn (BankLine $l): int => $l->id)->all());
        if ($undecided !== []) {
            return ['done' => false, 'undecided' => $undecided, 'changed' => [], 'difference' => null];
        }

        $changed = self::changedLines($lines, $decisions);
        if ($changed !== []) {
            foreach ($changed as $id) {
                unset($decisions[$id]);
            }
            self::record($statement, __('Cleared lines that changed meanwhile'), $decisions, $userId);

            return ['done' => false, 'undecided' => [], 'changed' => $changed, 'difference' => null];
        }

        try {
            DB::transaction(function () use ($statement, $lines, $decisions, $userId, $acceptDifference): void {
                foreach ($lines as $line) {
                    self::applyLine($line, $decisions[$line->id], $userId);
                }

                $wallet = Wallet::query()->findOrFail($statement->wallet_id);
                if ($statement->closing_balance !== null && ! $acceptDifference) {
                    $app = WalletBalances::all($statement->to_date)[$wallet->id] ?? (float) $wallet->starting_balance;
                    $difference = round((float) $statement->closing_balance - $app, 2);
                    if (abs($difference) >= 0.005) {
                        throw new BalanceDifference($difference);
                    }
                }

                $statement->forceFill(['status' => 'concluded', 'concluded_at' => Carbon::now(), 'concluded_by' => $userId])->save();
                self::concludeWallet($wallet, $statement->to_date->toDateString(), $userId, $statement->id);
            });
        } catch (BalanceDifference $e) {
            return ['done' => false, 'undecided' => [], 'changed' => [], 'difference' => $e->difference];
        }

        return ['done' => true, 'undecided' => [], 'changed' => [], 'difference' => null];
    }

    /** Declare a wallet's books complete up to a date (never moves it back). */
    public static function concludeWallet(Wallet $wallet, string $until, ?int $userId, ?int $statementId = null): void
    {
        if ($wallet->concluded_until === null || $wallet->concluded_until->toDateString() < $until) {
            $wallet->forceFill(['concluded_until' => $until])->save();
        }
        WalletConclusion::query()->create([
            'wallet_id' => $wallet->id,
            'bank_statement_id' => $statementId,
            'user_id' => $userId,
            'until' => $until,
        ]);
    }

    /**
     * Lines whose decision rests on something that changed since: a row deleted,
     * paired with another line or edited after the decision; a planned item gone or
     * with less open than the decision takes.
     *
     * @param  Collection<int, BankLine>  $lines
     * @param  array<int, list<array<string, mixed>>>  $decisions
     * @return list<int>
     */
    private static function changedLines(Collection $lines, array $decisions): array
    {
        $planUse = self::usedPlan($decisions);
        $changed = [];
        foreach ($lines as $line) {
            foreach ($decisions[$line->id] as $p) {
                $stale = match ($p['t']) {
                    'row' => ($t = Transaction::query()->find((int) ($p['id'] ?? 0))) === null
                        || $t->bank_line_id !== null
                        || $t->updated_at->greaterThan(Carbon::parse((string) $p['at'])->addSecond()),
                    'like' => Transaction::query()->find((int) ($p['id'] ?? 0)) === null,
                    'plan' => ($item = Plan::find((string) ($p['key'] ?? ''))) === null
                        || $item->open() < ($planUse[$item->key] ?? 0) - 0.005,
                    default => false,
                };
                if ($stale) {
                    $changed[] = $line->id;
                    break;
                }
            }
        }

        return $changed;
    }

    /**
     * Make a line's decision real and pair the line with what it is.
     *
     * @param  list<array<string, mixed>>  $pieces
     */
    private static function applyLine(BankLine $line, array $pieces, ?int $userId): void
    {
        $first = $pieces[0];
        if ($first['t'] === 'fee') {
            BankReconciler::addFee($line, Wallet::query()->findOrFail($line->wallet_id), $userId);

            return;
        }
        if ($first['t'] === 'like') {
            BankReconciler::addLike($line, Transaction::query()->with('vatLines', 'withheldLines')->findOrFail((int) ($first['id'] ?? 0)), $userId);

            return;
        }

        $rows = [];
        foreach ($pieces as $p) {
            match ($p['t']) {
                'row' => $rows[] = Transaction::query()->findOrFail((int) ($p['id'] ?? 0)),
                'plan' => $rows[] = self::payPlanItem($line, $p, $userId),
                'new' => array_push($rows, ...self::newRows($line, $p, $userId)),
                default => throw new RuntimeException("Unknown piece {$p['t']}"),
            };
        }
        BankReconciler::accept($line, $rows);
    }

    /**
     * A plan piece: the real row it becomes; the rest of a part-paid item now
     * expected on the piece's `rest` date (its invoice date stays put); a customer
     * payment line paid in full is issued.
     *
     * @param  array<string, mixed>  $p
     */
    private static function payPlanItem(BankLine $line, array $p, ?int $userId): Transaction
    {
        Plan::flush();
        $key = (string) ($p['key'] ?? '');
        $item = Plan::find($key) ?? throw new RuntimeException("Plan item {$key} is gone");
        $row = BankReconciler::fromPlan($line, $item, (float) $p['amount'], $userId);
        $rest = round($item->open() - (float) $p['amount'], 2);

        $planned = PlannedTransaction::query()->find($item->planned()?->id);
        if ($rest >= 0.005 && ($p['rest'] ?? null) !== null) {
            if ($planned !== null) {
                $planned->forceFill(['date' => $p['rest'], 'invoice_pinned' => true])->save();
            } else {
                PlanAdjustment::query()->updateOrCreate(['plan_key' => $item->key], ['date' => $p['rest'], 'user_id' => $userId]);
            }
        } elseif ($rest < 0.005 && $planned !== null && $planned->type === 'income') {
            $planned->forceFill(['is_issued' => true])->save();
        }

        return $row;
    }

    /**
     * A new-row piece: the row from the add form (and the bank fee paid with it).
     *
     * @param  array<string, mixed>  $p
     * @return list<Transaction>
     */
    private static function newRows(BankLine $line, array $p, ?int $userId): array
    {
        $data = (array) $p['data'];
        $row = new Transaction;
        $row->user_id = $userId;
        TransactionWriter::persist($row, $data);
        if (($data['managed_key'] ?? null) !== null) {
            $item = Plan::find((string) $data['managed_key']);
            if ($item !== null) {
                $row->forceFill([
                    'managed_key' => $item->key,
                    'source' => in_array($item->kind, ['recurrence', 'tax'], true) ? $item->kind : null,
                    'recurrence_id' => $item->template->recurrence_id,
                ])->save();
            }
        }
        $rows = [$row];

        $fee = (float) ($p['fee'] ?? 0);
        $wallet = Wallet::query()->find($line->wallet_id);
        if ($fee > 0 && $wallet?->takesBankFees()) {
            $feeRow = new Transaction;
            $feeRow->user_id = $userId;
            TransactionWriter::persist($feeRow, [
                'type' => 'expense', 'date' => $line->date->toDateString(), 'invoice_date' => $line->date->toDateString(),
                'description' => '', 'wallet_id' => $wallet->id, 'entity_id' => $wallet->fee_entity_id,
                'category_id' => $wallet->fee_category_id, 'amount_mode' => 'total',
                'lines' => [['amount' => $fee, 'vat_rate_id' => null]],
            ]);
            $rows[] = $feeRow;
        }

        return $rows;
    }

    /**
     * The cash a new row from the add form would move (with its bank fee), so the
     * draft knows how much of the line it covers.
     *
     * @param  array<string, mixed>  $data
     */
    public static function cashOf(array $data, ?float $fee = null): float
    {
        if ($data['type'] === 'transfer') {
            $cash = (float) $data['net'];
        } elseif (isset($data['category_id']) && AmountLines::isPayrollCategory((int) $data['category_id'])) {
            $cash = (float) $data['net'] - (float) ($data['fmy_amount'] ?? 0) - (float) ($data['efka_employee_amount'] ?? 0);
        } else {
            $r = AmountLines::resolve($data['lines'], $data['amount_mode'], $data['invoice_date']);
            $cash = $r['net'] + $r['vat_amount'] - $r['withheld_amount'];
        }

        return round($cash + (float) $fee, 2);
    }
}

<?php

namespace App\Support;

use App\Models\BankLine;
use App\Models\Transaction;
use App\Models\Wallet;

/**
 * The bank and the app side by side for one wallet and period: every statement line
 * next to the row(s) it is (or the app thinks it is), and every row of the app the
 * bank doesn't show on its own. A line that is several rows (a repayment and its cost
 * booked as one) shows its first row beside it and the others on lines of their own
 * just under it (`part_of`). Each pair gets a verdict:
 *
 * - **match**: paired, same day, same amount, reconciled — nothing to do;
 * - **differs**: the app believes it is the same movement, but a date or the amount
 *   differs, or the pair isn't confirmed yet (accepting makes the row like the bank);
 * - **bank_only**: at the bank, missing from the app;
 * - **app_only**: in the app, not at the bank;
 * - **decided**: a line of an open statement with its draft decision (nothing is
 *   written until the statement is concluded); **partial** while the decision
 *   doesn't cover the whole line yet.
 *
 * Rows come in the bank's order: by date, the bank's lines in their order within the
 * day, then the app's unpaired rows of that day.
 *
 * @phpstan-type Differences array{date?: string, amount?: float, unconfirmed?: true, unreconciled?: true}
 * @phpstan-type Row array{status: 'match'|'differs'|'bank_only'|'app_only'|'decided'|'partial', line: BankLine|null, part_of: BankLine|null, transaction: Transaction|null, linked: bool, differences: Differences, proposal: array{kind: 'fee'}|array{kind: 'like', transaction_id: int}|array{kind: 'plan', key: string}|null, decision: list<array<string, mixed>>|null}
 */
final class BankComparison
{
    /**
     * @param  array<int, list<array<string, mixed>>>  $decisions  an open statement's draft
     * @return list<Row>
     */
    public static function rows(Wallet $wallet, string $from, string $to, array $decisions = []): array
    {
        $lines = BankLine::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with('transactions.entity:id,name', 'transactions.category:id,name')
            ->orderBy('date')->orderBy('position')
            ->get();
        $usedRows = StatementDraft::usedRows($decisions);
        $suggestions = BankReconciler::suggestions(
            $wallet,
            $lines->filter(fn (BankLine $l): bool => $l->isPending() && ! isset($decisions[$l->id])),
            $usedRows,
            StatementDraft::usedPlan($decisions),
        );
        $guessed = Transaction::query()->with('entity:id,name', 'category:id,name')
            ->findMany(collect($suggestions)->pluck('proposal')->flatMap(fn (?array $p): array => match ($p['kind'] ?? null) {
                'match' => [$p['transaction_id']],
                'group' => $p['transaction_ids'],
                default => [],
            }))
            ->keyBy('id');

        $rows = [];
        $paired = [];
        foreach ($lines as $line) {
            $linked = $line->transactions->isNotEmpty();
            if (! $linked && isset($decisions[$line->id])) {
                $covered = StatementDraft::covered($decisions[$line->id]);
                $whole = abs($covered - abs((float) $line->amount)) < 0.005;
                $rows[] = self::row($whole ? 'decided' : 'partial', $line, null, null, false, $whole ? [] : ['amount' => $covered], null, $decisions[$line->id]);

                continue;
            }
            $proposal = $suggestions[$line->id]['proposal'] ?? null;
            $guessIds = match ($proposal['kind'] ?? null) {
                'match' => [$proposal['transaction_id']],
                'group' => $proposal['transaction_ids'],
                default => [],
            };
            $transactions = $linked
                ? $line->transactions->values()->all()
                : $guessed->only($guessIds)->values()->all();

            if ($transactions === []) {
                $rows[] = self::row('bank_only', $line, null, null, false, [], $proposal);

                continue;
            }

            $whole = self::differences($line, $transactions, $linked);
            $status = $whole === [] && array_all($transactions, fn (Transaction $t): bool => $t->date->isSameDay($line->date)) ? 'match' : 'differs';
            foreach ($transactions as $i => $t) {
                $paired[] = $t->id;
                $own = $t->date->isSameDay($line->date) ? [] : ['date' => $t->date->toDateString()];
                $rows[] = $i === 0
                    ? self::row($status, $line, null, $t, $linked, [...$own, ...$whole], null)
                    : self::row($status, null, $line, $t, $linked, $own, null);
            }
        }

        // The app's rows the bank doesn't show: counted rows of the wallet in the
        // period, paired with no line.
        $appOnly = Transaction::query()
            ->counted()
            ->with('entity:id,name', 'category:id,name')
            ->where(fn ($q) => $q->where('wallet_id', $wallet->id)->orWhere('to_wallet_id', $wallet->id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereNotIn('id', [...$paired, ...array_keys($usedRows)])
            ->whereNull('bank_line_id')
            ->inBankOrder()
            ->get();
        foreach ($appOnly as $t) {
            $rows[] = self::row('app_only', null, null, $t, false, [], null);
        }

        usort($rows, fn (array $a, array $b): int => self::sortKey($a) <=> self::sortKey($b));

        return $rows;
    }

    /**
     * What differs between a line and its row(s), beyond each row's own date: the
     * amount they move together where it isn't the bank's, and whether the pair is
     * still only the app's guess, or a paired row has lost its reconciled mark since.
     *
     * @param  array<int, Transaction>  $transactions
     * @return array{amount?: float, unconfirmed?: true, unreconciled?: true}
     */
    public static function differences(BankLine $line, array $transactions, bool $linked): array
    {
        $out = [];
        $cash = round(array_sum(array_map(fn (Transaction $t): float => BankReconciler::cash($t), $transactions)), 2);
        if (abs($cash - abs((float) $line->amount)) >= 0.005) {
            $out['amount'] = $cash;
        }
        if (! $linked) {
            $out['unconfirmed'] = true;
        } elseif (! array_all($transactions, fn (Transaction $t): bool => $t->is_reconciled)) {
            $out['unreconciled'] = true;
        }

        return $out;
    }

    /**
     * @param  'match'|'differs'|'bank_only'|'app_only'|'decided'|'partial'  $status
     * @param  Differences  $differences
     * @param  array{kind: 'match', transaction_id: int}|array{kind: 'group', transaction_ids: list<int>}|array{kind: 'plan', key: string}|array{kind: 'fee'}|array{kind: 'like', transaction_id: int}|null  $proposal
     * @param  list<array<string, mixed>>|null  $decision
     * @return Row
     */
    private static function row(string $status, ?BankLine $line, ?BankLine $partOf, ?Transaction $transaction, bool $linked, array $differences, ?array $proposal, ?array $decision = null): array
    {
        return [
            'status' => $status,
            'line' => $line,
            'part_of' => $partOf,
            'transaction' => $transaction,
            'linked' => $linked,
            'differences' => $differences,
            // A suggested pair is the row itself; what's left is how to add a row, or
            // the planned item it pays.
            'proposal' => $proposal !== null && in_array($proposal['kind'], ['fee', 'like', 'plan'], true) ? $proposal : null,
            'decision' => $decision,
        ];
    }

    /**
     * Date; within a day the bank's lines in their order (a line's further rows right
     * under it), then the app's own rows.
     *
     * @param  Row  $row
     * @return array{string, int, int, int, int}
     */
    private static function sortKey(array $row): array
    {
        $line = $row['line'] ?? $row['part_of'];
        $t = $row['transaction'];
        if ($line !== null) {
            return [$line->date->toDateString(), 0, $line->position, $line->id, $row['line'] !== null ? 0 : ($t->id ?? 0)];
        }

        return $t === null ? ['', 0, 0, 0, 0] : [$t->date->toDateString(), 1, $t->bank_position ?? PHP_INT_MAX, $t->id, 0];
    }
}

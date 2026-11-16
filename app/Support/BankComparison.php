<?php

namespace App\Support;

use App\Models\BankLine;
use App\Models\Transaction;
use App\Models\Wallet;

/**
 * The bank and the app side by side for one wallet and period: every statement line
 * next to the row it is (or the row the app thinks it is), and every row of the app
 * the bank doesn't show on its own. Each pair gets a verdict:
 *
 * - **match**: paired, same day, same amount, reconciled — nothing to do;
 * - **differs**: the app believes it is the same movement, but the date or amount
 *   differs, or the pair isn't confirmed yet (accepting makes the row like the bank);
 * - **bank_only**: at the bank, missing from the app;
 * - **app_only**: in the app, not at the bank.
 *
 * Rows come in the bank's order: by date, the bank's lines in their order within the
 * day, then the app's unpaired rows of that day.
 *
 * @phpstan-type Differences array{date?: string, amount?: float, unconfirmed?: true, unreconciled?: true}
 * @phpstan-type Row array{status: 'match'|'differs'|'bank_only'|'app_only', line: BankLine|null, transaction: Transaction|null, linked: bool, differences: Differences, proposal: array{kind: 'fee'}|array{kind: 'like', transaction_id: int}|null}
 */
final class BankComparison
{
    /**
     * @return list<Row>
     */
    public static function rows(Wallet $wallet, string $from, string $to): array
    {
        $lines = BankLine::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with('transaction.entity:id,name', 'transaction.category:id,name')
            ->orderBy('date')->orderBy('position')
            ->get();
        $suggestions = BankReconciler::suggestions($wallet, $lines->filter(fn (BankLine $l): bool => $l->isPending()));

        $rows = [];
        $paired = [];
        foreach ($lines as $line) {
            $suggestion = $suggestions[$line->id] ?? null;
            $proposal = $suggestion['proposal'] ?? null;
            $transaction = $line->transaction;
            $linked = $transaction !== null;
            if (! $linked && ($proposal['kind'] ?? null) === 'match') {
                $transaction = collect($suggestion['candidates'] ?? [])
                    ->first(fn (array $c): bool => $c['transaction']->id === $proposal['transaction_id'])['transaction'] ?? null;
            }

            if ($transaction === null) {
                $rows[] = self::row('bank_only', $line, null, false, [], $proposal);

                continue;
            }

            $paired[] = $transaction->id;
            $differences = self::differences($line, $transaction, $linked);
            $rows[] = self::row($differences === [] ? 'match' : 'differs', $line, $transaction, $linked, $differences, null);
        }

        // The app's rows the bank doesn't show: counted rows of the wallet in the
        // period, paired with no line.
        $appOnly = Transaction::query()
            ->counted()
            ->with('entity:id,name', 'category:id,name')
            ->where(fn ($q) => $q->where('wallet_id', $wallet->id)->orWhere('to_wallet_id', $wallet->id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereNotIn('id', $paired)
            ->whereNotIn('id', BankLine::query()->whereNotNull('transaction_id')->select('transaction_id'))
            ->inBankOrder()
            ->get();
        foreach ($appOnly as $t) {
            $rows[] = self::row('app_only', null, $t, false, [], null);
        }

        usort($rows, fn (array $a, array $b): int => self::sortKey($a) <=> self::sortKey($b));

        return $rows;
    }

    /**
     * What differs between a line and the row paired with it: the row's own date and
     * amount where they aren't the bank's, and whether the pair is still only the
     * app's guess, or a confirmed row has lost its reconciled mark since.
     *
     * @return Differences
     */
    public static function differences(BankLine $line, Transaction $t, bool $linked): array
    {
        $out = [];
        if (! $t->date->isSameDay($line->date)) {
            $out['date'] = $t->date->toDateString();
        }
        $cash = BankReconciler::cash($t);
        if (abs($cash - abs((float) $line->amount)) >= 0.005) {
            $out['amount'] = $cash;
        }
        if (! $linked) {
            $out['unconfirmed'] = true;
        } elseif (! $t->is_reconciled) {
            $out['unreconciled'] = true;
        }

        return $out;
    }

    /**
     * @param  'match'|'differs'|'bank_only'|'app_only'  $status
     * @param  Differences  $differences
     * @param  array{kind: 'match', transaction_id: int}|array{kind: 'fee'}|array{kind: 'like', transaction_id: int}|null  $proposal
     * @return Row
     */
    private static function row(string $status, ?BankLine $line, ?Transaction $transaction, bool $linked, array $differences, ?array $proposal): array
    {
        return [
            'status' => $status,
            'line' => $line,
            'transaction' => $transaction,
            'linked' => $linked,
            'differences' => $differences,
            // A suggested match is the pair itself; what's left is how to add the row.
            'proposal' => $proposal !== null && $proposal['kind'] !== 'match' ? $proposal : null,
        ];
    }

    /**
     * Date; within a day the bank's lines in their order, then the app's own rows.
     *
     * @param  Row  $row
     * @return array{string, int, int, int}
     */
    private static function sortKey(array $row): array
    {
        if ($row['line'] !== null) {
            return [$row['line']->date->toDateString(), 0, $row['line']->position, $row['line']->id];
        }
        $t = $row['transaction'];

        return $t === null ? ['', 0, 0, 0] : [$t->date->toDateString(), 1, $t->bank_position ?? PHP_INT_MAX, $t->id];
    }
}

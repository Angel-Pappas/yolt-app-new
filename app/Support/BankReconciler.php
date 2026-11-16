<?php

namespace App\Support;

use App\Models\BankLine;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bank statement lines against the app's transactions. Importing stores each new
 * line once; then every waiting line gets a suggestion, and applying a suggestion
 * (or choosing by hand) either confirms an existing transaction or adds a new one —
 * in both cases the transaction ends up reconciled, dated on the day the money
 * showed at the bank, moving exactly the bank's amount, and placed in the bank's
 * order within that day.
 *
 * Suggestions, in order of preference:
 * - **match** an existing row of the wallet going the same way with the same amount
 *   (unreconciled within 15 days, or already reconciled within 3 — a row typed in
 *   before the import), or the one unreconciled row whose entity's name is in the
 *   bank text (a salary that changed). Two equal candidates = no guess.
 * - **fee**: the bank's own charge (ΕΞΟΔΑ …) on a wallet that knows where fees go.
 * - **like**: a new row copied from the last time this bank text was seen, or from
 *   the latest row of the one entity whose name is in the text.
 *
 * @phpstan-type Proposal array{kind: 'match', transaction_id: int}|array{kind: 'fee'}|array{kind: 'like', transaction_id: int}
 */
final class BankReconciler
{
    /** Days either side of a line an unreconciled row may sit to match it. */
    private const OPEN_WINDOW = 15;

    /** Days either side for a row already reconciled (it was dated by the bank). */
    private const RECONCILED_WINDOW = 3;

    /**
     * Days either side for a row that only names its entity (different amount) — a
     * salary that changed is paid on its day; a week off is another payment.
     */
    private const NAMED_WINDOW = 3;

    /**
     * Store a parsed statement's lines (those not imported before) and the balance
     * it shows. A line seen before keeps its decision; only its place in the day is
     * refreshed, in case the bank added later movements to that day.
     *
     * @param  array{balance: float|null, balance_date: string|null, lines: list<array{date: string, position: int, text: string, amount: float, reference: string, fingerprint: string}>}  $statement
     * @return array{added: int, known: int}
     */
    public static function import(Wallet $wallet, array $statement, ?int $userId): array
    {
        $existing = BankLine::query()->where('wallet_id', $wallet->id)
            ->whereIn('fingerprint', array_column($statement['lines'], 'fingerprint'))
            ->get()->keyBy('fingerprint');

        $added = 0;
        foreach ($statement['lines'] as $line) {
            $known = $existing->get($line['fingerprint']);
            if ($known !== null) {
                if ($known->position !== $line['position']) {
                    $known->update(['position' => $line['position']]);
                    if ($known->transaction_id !== null) {
                        Transaction::query()->whereKey($known->transaction_id)->update(['bank_position' => $line['position']]);
                    }
                }

                continue;
            }

            $new = new BankLine([...$line, 'wallet_id' => $wallet->id]);
            $new->user_id = $userId;
            $new->save();
            $added++;
        }

        // The latest statement wins: an older export never replaces a newer balance.
        if ($statement['balance'] !== null && $statement['balance_date'] !== null
            && ($wallet->statement_date === null || $wallet->statement_date->toDateString() <= $statement['balance_date'])) {
            $wallet->forceFill([
                'statement_balance' => $statement['balance'],
                'statement_date' => $statement['balance_date'],
            ])->save();
        }

        return ['added' => $added, 'known' => count($statement['lines']) - $added];
    }

    /**
     * Every waiting line of a wallet with its candidates and suggestion. Lines are
     * taken earliest first and a suggested match is used up, so two lines never
     * suggest the same row.
     *
     * @param  Collection<int, BankLine>  $lines  pending lines of one wallet
     * @return array<int, array{candidates: list<array{transaction: Transaction, exact: bool}>, proposal: Proposal|null}>
     */
    public static function suggestions(Wallet $wallet, Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $lines = $lines->sortBy(fn (BankLine $l): string => $l->date->toDateString().sprintf('%06d', $l->position))->values();
        $pool = self::pool($wallet, $lines);
        $entities = Entity::query()->get(['id', 'name']);
        $history = self::history($wallet);
        $used = [];
        $out = [];

        foreach ($lines as $line) {
            $candidates = self::candidates($wallet, $line, $pool, $used);
            $proposal = self::chooseMatch($line, $candidates);
            if ($proposal !== null) {
                $used[$proposal['transaction_id']] = true;
            } elseif (array_filter($candidates, fn (array $c): bool => $c['exact']) !== []) {
                // Rows with this very amount exist but none stands out: the person
                // picks — adding a new one would likely duplicate.
            } elseif ($wallet->takesBankFees() && BankText::isBankCharge($line->text)) {
                $proposal = ['kind' => 'fee'];
            } else {
                $template = self::template($line, $history, $entities);
                $proposal = $template !== null ? ['kind' => 'like', 'transaction_id' => $template->id] : null;
            }

            $out[$line->id] = ['candidates' => $candidates, 'proposal' => $proposal];
        }

        return $out;
    }

    /**
     * The rows of the wallet the lines could confirm: in the lines' date range (with
     * margin) and not yet confirmed by another statement line.
     *
     * @param  Collection<int, BankLine>  $lines
     * @return Collection<int, Transaction>
     */
    private static function pool(Wallet $wallet, Collection $lines): Collection
    {
        $from = Carbon::parse($lines->min(fn (BankLine $l) => $l->date->toDateString()))->subDays(self::OPEN_WINDOW);
        $to = Carbon::parse($lines->max(fn (BankLine $l) => $l->date->toDateString()))->addDays(self::OPEN_WINDOW);

        return Transaction::query()
            ->with('entity:id,name', 'category:id,name')
            ->where(fn ($q) => $q->where('wallet_id', $wallet->id)->orWhere('to_wallet_id', $wallet->id))
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereNotIn('id', BankLine::query()->whereNotNull('transaction_id')->select('transaction_id'))
            ->inBankOrder()
            ->get();
    }

    /**
     * The rows a line could be: same wallet and direction, close in date; equal in
     * amount (exact) or, for an unreconciled row, naming its entity. Best first.
     *
     * @param  Collection<int, Transaction>  $pool
     * @param  array<int, true>  $used
     * @return list<array{transaction: Transaction, exact: bool}>
     */
    public static function candidates(Wallet $wallet, BankLine $line, Collection $pool, array $used = []): array
    {
        $amount = abs((float) $line->amount);
        $incoming = (float) $line->amount > 0;
        $scored = [];

        foreach ($pool as $t) {
            if (isset($used[$t->id]) || ! self::sameDirection($t, $wallet->id, $incoming)) {
                continue;
            }
            $days = (int) abs($t->date->diffInDays($line->date));
            $exact = abs(self::cash($t) - $amount) < 0.005;
            $named = $t->entity !== null ? BankText::nameScore($t->entity->name, $line->text) : 0;

            $ratio = $amount > 0 ? self::cash($t) / $amount : 0.0;
            $fits = match (true) {
                $t->is_reconciled => $exact && $days <= self::RECONCILED_WINDOW,
                $exact => $days <= self::OPEN_WINDOW,
                // Named only: close in date and of a similar size.
                default => $named > 0 && $days <= self::NAMED_WINDOW && $ratio >= 0.5 && $ratio <= 2,
            };
            if (! $fits) {
                continue;
            }

            $scored[] = ['transaction' => $t, 'exact' => $exact, 'named' => $named, 'days' => $days];
        }

        usort($scored, fn (array $a, array $b): int => [$b['exact'], $b['named'], $a['days'], $a['transaction']->id]
            <=> [$a['exact'], $a['named'], $b['days'], $b['transaction']->id]);

        return array_map(
            fn (array $c): array => ['transaction' => $c['transaction'], 'exact' => $c['exact']],
            array_slice($scored, 0, 5),
        );
    }

    /**
     * The match to suggest, if one is clear: the only same-amount row, or the one
     * that stands out by naming its entity, sitting on the same day, or being already
     * reconciled (the one typed in by hand, next to its automatic duplicate); failing
     * an amount, the only unreconciled row naming its entity.
     *
     * @param  list<array{transaction: Transaction, exact: bool}>  $candidates
     * @return array{kind: 'match', transaction_id: int}|null
     */
    private static function chooseMatch(BankLine $line, array $candidates): ?array
    {
        $exact = array_values(array_filter($candidates, fn (array $c): bool => $c['exact']));
        if (count($exact) === 1) {
            return ['kind' => 'match', 'transaction_id' => $exact[0]['transaction']->id];
        }
        if (count($exact) > 1) {
            $rank = fn (array $c): array => [
                $c['transaction']->entity !== null ? BankText::nameScore($c['transaction']->entity->name, $line->text) : 0,
                $c['transaction']->date->isSameDay($line->date) ? 1 : 0,
                $c['transaction']->is_reconciled ? 1 : 0,
            ];
            $ranks = array_map($rank, $exact);
            $best = max($ranks);
            $top = array_keys($ranks, $best, true);

            return count($top) === 1 && $best !== [0, 0, 0]
                ? ['kind' => 'match', 'transaction_id' => $exact[$top[0]]['transaction']->id]
                : null;
        }
        // No same amount: a row naming the entity is that payment at a new amount.
        if (count($candidates) === 1) {
            return ['kind' => 'match', 'transaction_id' => $candidates[0]['transaction']->id];
        }

        return null;
    }

    /**
     * What earlier statement lines of the wallet became, newest first, by the stable
     * part of their text — so the same merchant suggests the same row again.
     *
     * @return array<string, list<Transaction>>
     */
    private static function history(Wallet $wallet): array
    {
        $out = [];
        BankLine::query()
            ->where('wallet_id', $wallet->id)
            ->whereHas('transaction')
            ->with('transaction.vatLines', 'transaction.withheldLines')
            ->orderByDesc('date')->orderByDesc('position')
            ->limit(2000)
            ->get()
            ->each(function (BankLine $l) use (&$out): void {
                $key = BankText::key($l->text);
                if ($key !== '' && $l->transaction !== null && count($out[$key] ?? []) < 5) {
                    $out[$key][] = $l->transaction;
                }
            });

        return $out;
    }

    /**
     * The row to copy for a new line: the last row this bank text became, if its
     * recent rows all went to the same entity the same way; else the latest row of
     * the one entity whose name is in the text. Payroll is never copied (its amounts
     * need the payroll fields).
     *
     * @param  array<string, list<Transaction>>  $history
     * @param  Collection<int, Entity>  $entities
     */
    private static function template(BankLine $line, array $history, Collection $entities): ?Transaction
    {
        $past = $history[BankText::key($line->text)] ?? [];
        if ($past !== []) {
            $kinds = array_unique(array_map(fn (Transaction $t): string => $t->type.'|'.$t->entity_id.'|'.$t->to_wallet_id, $past));

            return count($kinds) === 1 && ! self::isPayroll($past[0]) ? $past[0] : null;
        }

        $scores = $entities->mapWithKeys(fn (Entity $e): array => [$e->id => BankText::nameScore($e->name, $line->text)])
            ->filter(fn (int $s): bool => $s > 0);
        if ($scores->isEmpty()) {
            return null;
        }
        $best = $scores->max();
        $top = $scores->filter(fn (int $s): bool => $s === $best);
        if ($top->count() !== 1) {
            return null;
        }

        $latest = Transaction::query()
            ->where('entity_id', $top->keys()->first())
            ->where('type', '!=', 'transfer')
            ->orderByDesc('date')->orderByDesc('id')
            ->first();

        return $latest !== null && ! self::isPayroll($latest) ? $latest : null;
    }

    /**
     * Confirm an existing row with a statement line: dated on the bank's day (the
     * invoice date follows while it still equals the date), moving the bank's
     * amount, reconciled, switched on, and placed in the bank's order.
     */
    public static function link(BankLine $line, Transaction $transaction): void
    {
        if ($transaction->invoice_date->isSameDay($transaction->date)) {
            $transaction->invoice_date = $line->date;
        }
        $transaction->date = $line->date;
        TransactionWriter::toCash($transaction, abs((float) $line->amount));
        $transaction->is_reconciled = true;
        $transaction->is_excluded = false;
        $transaction->bank_position = $line->position;
        $transaction->save();

        $line->transaction_id = $transaction->id;
        $line->is_dismissed = false;
        $line->save();
    }

    /** Add the line as the bank's own charge, to the wallet's fee entity and category. */
    public static function addFee(BankLine $line, Wallet $wallet, ?int $userId): Transaction
    {
        return self::create($line, $userId, [
            'type' => 'expense',
            'entity_id' => $wallet->fee_entity_id,
            'category_id' => $wallet->fee_category_id,
            'amount_mode' => 'total',
            'lines' => [['amount' => abs((float) $line->amount), 'vat_rate_id' => null]],
        ]);
    }

    /**
     * Add the line as a new row shaped like `$template`: same entity, category, VAT
     * rate and withholding (or the same other wallet, for a transfer). A line going
     * the other way from its template — a supplier's refund — becomes an income from
     * that entity, without a category (categories are per side).
     */
    public static function addLike(BankLine $line, Transaction $template, ?int $userId): Transaction
    {
        $incoming = (float) $line->amount > 0;
        $amount = abs((float) $line->amount);

        if ($template->type === 'transfer') {
            $other = $template->wallet_id === $line->wallet_id ? $template->to_wallet_id : $template->wallet_id;

            return self::create($line, $userId, [
                'type' => 'transfer',
                'wallet_id' => $incoming ? $other : $line->wallet_id,
                'to_wallet_id' => $incoming ? $line->wallet_id : $other,
                'net' => $amount,
            ]);
        }

        $type = $incoming ? 'income' : 'expense';
        $vatLine = $template->vatLines->sortBy('position')->first();
        $withheld = $template->withheldLines->sortBy('position')->first();

        return self::create($line, $userId, [
            'type' => $type,
            'entity_id' => $template->entity_id,
            'category_id' => $template->type === $type ? $template->category_id : null,
            'amount_mode' => 'total',
            'lines' => [[
                'amount' => $amount,
                'vat_rate_id' => $vatLine !== null ? $vatLine->vat_rate_id : $template->vat_rate_id,
                'withheld_rate_id' => $withheld?->withheld_rate_id,
            ]],
        ]);
    }

    /**
     * A new reconciled row for the line from partial form data.
     *
     * @param  array<string, mixed>  $data
     */
    private static function create(BankLine $line, ?int $userId, array $data): Transaction
    {
        $transaction = new Transaction;
        $transaction->user_id = $userId;
        TransactionWriter::persist($transaction, [
            'date' => $line->date->toDateString(),
            'invoice_date' => $line->date->toDateString(),
            'description' => '',
            'wallet_id' => $line->wallet_id,
            ...$data,
        ]);
        self::link($line, $transaction);

        return $transaction;
    }

    /** The cash a row moves, as a positive amount. */
    private static function cash(Transaction $t): float
    {
        return round($t->type === 'transfer' ? (float) $t->net : WalletBalances::cashTotal($t), 2);
    }

    /** Whether the row moves money into (or out of) the wallet, as the line does. */
    private static function sameDirection(Transaction $t, int $walletId, bool $incoming): bool
    {
        return match ($t->type) {
            'income' => $incoming && $t->wallet_id === $walletId,
            'expense' => ! $incoming && $t->wallet_id === $walletId,
            'transfer' => $incoming ? $t->to_wallet_id === $walletId : $t->wallet_id === $walletId,
            default => false,
        };
    }

    private static function isPayroll(Transaction $t): bool
    {
        return AmountLines::isPayrollCategory($t->category_id);
    }

    /**
     * Rows the bank doesn't show: the wallet's unreconciled rows (that count) dated
     * within the statement's span — typically an automatic row left next to the one
     * typed by hand, or a payment that didn't happen.
     *
     * @return array{count: int, from: string, to: string}|null
     */
    public static function notInBank(Wallet $wallet): ?array
    {
        $from = BankLine::query()->where('wallet_id', $wallet->id)->min('date');
        if ($from === null || $wallet->statement_date === null) {
            return null;
        }
        $from = Carbon::parse($from)->toDateString();
        $to = $wallet->statement_date->toDateString();

        $count = Transaction::query()
            ->counted()
            ->where('is_reconciled', false)
            ->where(fn ($q) => $q->where('wallet_id', $wallet->id)->orWhere('to_wallet_id', $wallet->id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->count();

        return ['count' => $count, 'from' => $from, 'to' => $to];
    }
}

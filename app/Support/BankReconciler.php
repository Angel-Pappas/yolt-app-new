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
 * line once and settles what is certain: a line with exactly one row of the same
 * amount on the same day (or rows on that day adding up to it) is paired, and the
 * bank's own charges become fee rows. Every other line gets a suggestion for the Bank
 * import page. A line is paired with one row or several (`transactions.bank_line_id`);
 * a paired row is reconciled, dated on the day the money showed at the bank, and
 * placed in the bank's order within that day.
 *
 * Suggestions, in order of preference:
 * - **match** an existing row of the wallet going the same way with the same amount
 *   (unreconciled within 15 days, or already reconciled within 3 — a row typed in
 *   before the import), or the one unreconciled row whose entity's name is in the
 *   bank text (a salary that changed). Two equal candidates = no guess.
 * - **fee**: the bank's own charge (ΕΞΟΔΑ …) on a wallet that knows where fees go.
 * - **group**: two or three rows within 3 days that add up to the line exactly — the
 *   bank booked them as one (a repayment and its cost). Only a combination that stands
 *   out: the one on the line's day, or the only one.
 * - **like**: a new row copied from the last time this bank text was seen, or from
 *   the latest row of the one entity whose name is in the text.
 *
 * @phpstan-type Proposal array{kind: 'match', transaction_id: int}|array{kind: 'group', transaction_ids: list<int>}|array{kind: 'fee'}|array{kind: 'like', transaction_id: int}
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
                    Transaction::query()->where('bank_line_id', $known->id)->update(['bank_position' => $line['position']]);
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
     * Settle a wallet's waiting lines that need no decision: a suggested row with the
     * line's very amount on the line's very day (or suggested rows on that day adding
     * up to it) is paired, and the bank's own charge becomes a fee row. Returns how
     * many lines were settled.
     */
    public static function settle(Wallet $wallet, ?int $userId): int
    {
        $lines = BankLine::query()->where('wallet_id', $wallet->id)->pending()->get();
        $suggestions = self::suggestions($wallet, $lines);
        $settled = 0;

        foreach ($lines as $line) {
            $proposal = $suggestions[$line->id]['proposal'] ?? null;
            if ($proposal === null || $proposal['kind'] === 'like') {
                continue;
            }
            if ($proposal['kind'] === 'fee') {
                self::addFee($line, $wallet, $userId);
                $settled++;

                continue;
            }
            if ($proposal['kind'] === 'group') {
                $rows = Transaction::query()->findMany($proposal['transaction_ids']);
                if ($rows->every(fn (Transaction $t): bool => $t->date->isSameDay($line->date))) {
                    self::accept($line, $rows->values()->all());
                    $settled++;
                }

                continue;
            }
            $match = collect($suggestions[$line->id]['candidates'])
                ->first(fn (array $c): bool => $c['transaction']->id === $proposal['transaction_id']);
            if ($match !== null && $match['exact'] && $match['transaction']->date->isSameDay($line->date)) {
                self::link($line, $match['transaction']);
                $settled++;
            }
        }

        return $settled;
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

        // First every line's own row, so a row that is exactly one line's is never
        // used up as part of another line's group.
        foreach ($lines as $line) {
            $candidates = self::candidates($wallet, $line, $pool, $used);
            $proposal = self::chooseMatch($line, $candidates);
            if ($proposal !== null) {
                $used[$proposal['transaction_id']] = true;
            }
            $out[$line->id] = ['candidates' => $candidates, 'proposal' => $proposal];
        }

        foreach ($lines as $line) {
            if ($out[$line->id]['proposal'] !== null
                // Rows with this very amount exist but none stands out: the person
                // picks — adding a new one would likely duplicate.
                || array_filter($out[$line->id]['candidates'], fn (array $c): bool => $c['exact']) !== []) {
                continue;
            }
            if ($wallet->takesBankFees() && BankText::isBankCharge($line->text)) {
                $out[$line->id]['proposal'] = ['kind' => 'fee'];

                continue;
            }
            $group = self::group($wallet, $line, $pool, $used);
            if ($group !== null) {
                foreach ($group as $id) {
                    $used[$id] = true;
                }
                $out[$line->id]['proposal'] = ['kind' => 'group', 'transaction_ids' => $group];

                continue;
            }
            $template = self::template($line, $history, $entities);
            if ($template !== null) {
                $out[$line->id]['proposal'] = ['kind' => 'like', 'transaction_id' => $template->id];
            }
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
            ->whereNull('bank_line_id')
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
     * Two or three rows that add up to the line exactly, all going its way within 3
     * days of it: the combination on the line's own day if only one is, else the only
     * combination there is.
     *
     * @param  Collection<int, Transaction>  $pool
     * @param  array<int, true>  $used
     * @return list<int>|null
     */
    private static function group(Wallet $wallet, BankLine $line, Collection $pool, array $used): ?array
    {
        $target = (int) round(abs((float) $line->amount) * 100);
        $incoming = (float) $line->amount > 0;
        $rows = $pool
            ->filter(fn (Transaction $t): bool => ! isset($used[$t->id])
                && self::sameDirection($t, $wallet->id, $incoming)
                && abs($t->date->diffInDays($line->date)) <= self::RECONCILED_WINDOW)
            ->map(fn (Transaction $t): array => ['t' => $t, 'cents' => (int) round(self::cash($t) * 100)])
            ->filter(fn (array $r): bool => $r['cents'] > 0 && $r['cents'] < $target)
            ->sortBy(fn (array $r): int => (int) abs($r['t']->date->diffInDays($line->date)))
            ->take(40)
            ->values()
            ->all();

        $found = [];
        $n = count($rows);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $two = $rows[$i]['cents'] + $rows[$j]['cents'];
                if ($two === $target) {
                    $found[] = [$rows[$i]['t'], $rows[$j]['t']];
                }
                for ($k = $j + 1; $k < $n && $two < $target; $k++) {
                    if ($two + $rows[$k]['cents'] === $target) {
                        $found[] = [$rows[$i]['t'], $rows[$j]['t'], $rows[$k]['t']];
                    }
                }
            }
        }

        $sameDay = array_values(array_filter($found, fn (array $combo): bool => array_all($combo, fn (Transaction $t): bool => $t->date->isSameDay($line->date))));
        $pick = match (true) {
            count($sameDay) === 1 => $sameDay[0],
            $sameDay === [] && count($found) === 1 => $found[0],
            default => null,
        };

        return $pick === null ? null : array_map(fn (Transaction $t): int => $t->id, $pick);
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
            ->whereHas('transactions')
            ->with('transactions.vatLines', 'transactions.withheldLines')
            ->orderByDesc('date')->orderByDesc('position')
            ->limit(2000)
            ->get()
            ->each(function (BankLine $l) use (&$out): void {
                $key = BankText::key($l->text);
                // A line that was several rows says nothing about what one row is.
                if ($key !== '' && $l->transactions->count() === 1 && count($out[$key] ?? []) < 5) {
                    $out[$key][] = $l->transactions->first();
                }
            });

        return $out;
    }

    /**
     * The row to copy for a new line: the last row this bank text became, if its
     * recent rows all went to the same entity the same way; else the latest row (up
     * to the line's day, reconciled first) of the one entity whose name is in the
     * text. Payroll is never copied (its amounts
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

        // What really happened before this line beats a projected row.
        $latest = Transaction::query()
            ->where('entity_id', $top->keys()->first())
            ->where('type', '!=', 'transfer')
            ->whereDate('date', '<=', $line->date->toDateString())
            ->orderByDesc('is_reconciled')->orderByDesc('date')->orderByDesc('id')
            ->first();

        return $latest !== null && ! self::isPayroll($latest) ? $latest : null;
    }

    /**
     * Make a row the line's only one, like the bank: dated on the bank's day, moving
     * the bank's amount, reconciled, switched on, in the bank's order. The line's
     * other rows are let go.
     */
    public static function link(BankLine $line, Transaction $transaction): void
    {
        self::release($line, except: [$transaction->id]);
        TransactionWriter::toCash($transaction, abs((float) $line->amount));
        self::attach($line, $transaction);
    }

    /**
     * Pair the line with exactly these rows: one is made like the bank ({@see link()});
     * several keep their own amounts (they add up to the line).
     *
     * @param  array<int, Transaction>  $transactions
     */
    public static function accept(BankLine $line, array $transactions): void
    {
        $transactions = array_values($transactions);
        if (count($transactions) === 1) {
            self::link($line, $transactions[0]);

            return;
        }
        self::release($line, except: array_map(fn (Transaction $t): int => $t->id, $transactions));
        foreach ($transactions as $t) {
            self::attach($line, $t);
        }
    }

    /**
     * Pair a row with the line by hand (dragged onto it, or chosen): it joins the
     * line's rows while they don't add up to the line yet, else it takes their place.
     * Its amount stays its own — if it differs, the page says so.
     */
    public static function pair(BankLine $line, Transaction $transaction): void
    {
        $others = $line->transactions()->whereKeyNot($transaction->id)->get();
        $owed = abs((float) $line->amount) - $others->sum(fn (Transaction $t): float => self::cash($t));
        if ($others->isEmpty() || abs($owed) < 0.005) {
            self::release($line, except: [$transaction->id]);
        }
        self::attach($line, $transaction);
    }

    /**
     * Let go of a line's rows (all, or one); they stay as they are, reconciled.
     *
     * @param  list<int>  $except
     */
    public static function release(BankLine $line, ?Transaction $only = null, array $except = []): void
    {
        Transaction::query()->where('bank_line_id', $line->id)
            ->when($only !== null, fn ($q) => $q->whereKey($only?->id))
            ->whereNotIn('id', $except)
            ->update(['bank_line_id' => null, 'bank_position' => null]);
        $line->unsetRelation('transactions');
    }

    /** Pair a row with the line as it is: on the bank's day, reconciled, in its place. */
    private static function attach(BankLine $line, Transaction $transaction): void
    {
        if ($transaction->invoice_date->isSameDay($transaction->date)) {
            $transaction->invoice_date = $line->date;
        }
        $transaction->date = $line->date;
        $transaction->is_reconciled = true;
        $transaction->is_excluded = false;
        $transaction->bank_position = $line->position;
        $transaction->bank_line_id = $line->id;
        $transaction->save();
        $line->unsetRelation('transactions');
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
    public static function cash(Transaction $t): float
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
}

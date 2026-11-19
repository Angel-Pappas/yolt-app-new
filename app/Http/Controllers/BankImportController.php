<?php

namespace App\Http\Controllers;

use App\Models\BankLine;
use App\Models\BankStatement;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletConclusion;
use App\Support\AlphaStatement;
use App\Support\BankComparison;
use App\Support\BankReconciler;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\StatementDraft;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounting ▸ Bank import (Angel, 2026-11-19). Three views:
 *
 *  - **Statements**: every upload (dates, who, Open or Concluded) and every wallet
 *    concluded by hand — where the books stand; upload a new statement here, and
 *    conclude a wallet without statements up to a date.
 *  - **Working on** an open statement: the bank and the app side by side
 *    ({@see BankComparison}); each decision is a draft step ({@see StatementDraft}),
 *    undoable, and nothing changes in the books until **Conclude**.
 *  - **Not matched yet**: planned items dated in a concluded period that nothing
 *    paid — link each to its transaction, move its date, or cancel it.
 */
class BankImportController extends Controller
{
    public function index(Request $request): Response
    {
        $wallets = Wallet::query()->orderBy('name')->get();
        $open = BankStatement::query()->where('status', 'open')->latest('id')->get();
        $statement = $request->filled('statement')
            ? BankStatement::query()->find((int) $request->input('statement'))
            : $open->first();
        $tab = in_array($request->input('tab'), ['statements', 'work', 'not-matched'], true)
            ? $request->input('tab')
            : ($statement !== null ? 'work' : 'statements');

        return Inertia::render('bank-import/index', [
            'tab' => $tab,
            'wallets' => $wallets->map(fn (Wallet $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'concluded_until' => $w->concluded_until?->toDateString(),
                'has_open' => $open->contains('wallet_id', $w->id),
            ])->values(),
            'history' => self::history($wallets),
            'statement' => $statement !== null ? self::work($statement) : null,
            'notMatched' => self::notMatched($wallets),
        ]);
    }

    /** Upload a statement: a new open statement with the file's new lines. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'file' => ['required', 'file', 'max:5120'],
        ]);
        $wallet = Wallet::query()->findOrFail((int) $data['wallet_id']);
        if (BankStatement::query()->where('wallet_id', $wallet->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['file' => __('Conclude or discard the open statement of :wallet first.', ['wallet' => $wallet->name])]);
        }

        try {
            $parsed = AlphaStatement::parse((string) $request->file('file')?->get());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        if ($parsed['lines'] === []) {
            throw ValidationException::withMessages(['file' => __('The file has no lines.')]);
        }

        $dates = array_column($parsed['lines'], 'date');
        $statement = DB::transaction(function () use ($wallet, $parsed, $dates, $request): BankStatement {
            $to = max($dates);
            $statement = new BankStatement([
                'wallet_id' => $wallet->id,
                'from_date' => min($dates),
                'to_date' => $to,
                'closing_balance' => $parsed['balance'] !== null && $parsed['balance_date'] !== null && $parsed['balance_date'] >= $to
                    ? $parsed['balance'] : null,
            ]);
            $statement->user_id = $request->user()->id;
            $statement->save();
            BankReconciler::import($wallet, $parsed, $request->user()->id, $statement->id);
            StatementDraft::settle($statement, $request->user()->id);

            return $statement;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statement uploaded — nothing changes in the books until you conclude it.')]);

        return redirect()->route('bank-import.index', ['tab' => 'work', 'statement' => $statement->id]);
    }

    /** Add a piece to a line's decision (a row, a planned item, the fee, a copy). */
    public function piece(Request $request, BankStatement $statement): RedirectResponse
    {
        $this->ensureOpen($statement);
        $data = $request->validate([
            'line_id' => ['required', 'integer'],
            't' => ['required', Rule::in(['row', 'plan', 'fee', 'like'])],
            'id' => ['required_if:t,row,like', 'integer'],
            'key' => ['required_if:t,plan', 'string'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'rest' => ['nullable', 'date'],
        ]);
        $line = BankLine::query()->where('bank_statement_id', $statement->id)->findOrFail((int) $data['line_id']);
        $decisions = StatementDraft::decisions($statement);
        $lineAmount = abs((float) $line->amount);
        $covered = StatementDraft::covered($decisions[$line->id] ?? []);
        $left = $covered >= $lineAmount - 0.005 ? $lineAmount : round($lineAmount - $covered, 2);

        if ($data['t'] === 'fee') {
            abort_unless($statement->wallet->takesBankFees(), 422);
        }
        [$piece, $label] = match ($data['t']) {
            'row' => $this->rowPiece((int) $data['id']),
            'plan' => $this->planPiece($statement, $line, $data, $left),
            'fee' => [['t' => 'fee', 'amount' => $lineAmount], __('Bank fee')],
            'like' => [['t' => 'like', 'id' => Transaction::query()->findOrFail((int) $data['id'])->id, 'amount' => $lineAmount], __('Added like an earlier row')],
            default => abort(422),
        };
        StatementDraft::addPiece($statement, $line, $piece, $label, $request->user()->id);

        return back();
    }

    /** Accept the suggestion of each chosen line. */
    public function apply(Request $request, BankStatement $statement): RedirectResponse
    {
        $this->ensureOpen($statement);
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        $count = StatementDraft::applySuggestions($statement, array_values(array_map('intval', $data['ids'])), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count line decided.|:count lines decided.', $count, ['count' => $count])]);

        return back();
    }

    /** Take a line's decision back. */
    public function clear(Request $request, BankStatement $statement, BankLine $line): RedirectResponse
    {
        $this->ensureOpen($statement);
        abort_unless($line->bank_statement_id === $statement->id, 404);
        StatementDraft::clearLine($statement, $line, $request->user()->id);

        return back();
    }

    /** Undo the latest step. */
    public function undo(BankStatement $statement): RedirectResponse
    {
        $this->ensureOpen($statement);
        $label = StatementDraft::lastStep($statement);
        StatementDraft::undo($statement);
        if ($label !== null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Undone: :what', ['what' => $label])]);
        }

        return back();
    }

    /**
     * Conclude: every line decided, nothing changed meanwhile, the balance as the
     * bank's (or the difference confirmed) — then the books take it all at once.
     */
    public function conclude(Request $request, BankStatement $statement): RedirectResponse
    {
        $this->ensureOpen($statement);
        $data = $request->validate(['accept_difference' => ['sometimes', 'boolean']]);
        $result = StatementDraft::conclude($statement, $request->user()->id, (bool) ($data['accept_difference'] ?? false));

        if ($result['undecided'] !== []) {
            throw ValidationException::withMessages(['conclude' => trans_choice(
                '{1} 1 line still needs a decision.|[2,*] :count lines still need a decision.',
                count($result['undecided']),
                ['count' => count($result['undecided'])],
            )]);
        }
        if ($result['changed'] !== []) {
            throw ValidationException::withMessages(['conclude' => trans_choice(
                '{1} 1 line was cleared: what it was paired with changed meanwhile. Decide it again.|[2,*] :count lines were cleared: what they were paired with changed meanwhile. Decide them again.',
                count($result['changed']),
                ['count' => count($result['changed'])],
            )]);
        }
        if ($result['difference'] !== null) {
            throw ValidationException::withMessages(['difference' => (string) $result['difference']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Concluded — :wallet is complete up to :date.', [
            'wallet' => $statement->wallet->name,
            'date' => $statement->to_date->format('d/m/Y'),
        ])]);

        return redirect()->route('bank-import.index', ['tab' => 'statements']);
    }

    /** Discard an open statement: its draft and the lines it brought. */
    public function destroy(BankStatement $statement): RedirectResponse
    {
        $this->ensureOpen($statement);
        DB::transaction(function () use ($statement): void {
            BankLine::query()->where('bank_statement_id', $statement->id)->pending()->delete();
            $statement->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statement discarded.')]);

        return redirect()->route('bank-import.index', ['tab' => 'statements']);
    }

    /** Conclude a wallet without statements up to a date, by hand. */
    public function concludeWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate(['until' => ['required', 'date', 'before_or_equal:today']]);
        StatementDraft::concludeWallet($wallet, Carbon::parse($data['until'])->toDateString(), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':wallet is complete up to :date.', [
            'wallet' => $wallet->name, 'date' => Carbon::parse($data['until'])->format('d/m/Y'),
        ])]);

        return back();
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function rowPiece(int $id): array
    {
        $t = Transaction::query()->findOrFail($id);
        if ($t->bank_line_id !== null) {
            throw ValidationException::withMessages(['piece' => __('That row is already a bank line.')]);
        }

        return [['t' => 'row', 'id' => $t->id, 'amount' => BankReconciler::cash($t)], __('Paired with a row')];
    }

    /**
     * A planned item: as much as the line still needs, up to what is open; a part
     * leaves the rest expected on a new date (asked for).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function planPiece(BankStatement $statement, BankLine $line, array $data, float $left): array
    {
        $candidate = BankReconciler::planCandidates($line, StatementDraft::usedPlan(StatementDraft::decisions($statement)))
            ->first(fn (array $c): bool => $c['item']->key === $data['key']);
        if ($candidate === null) {
            throw ValidationException::withMessages(['piece' => __('That planned item is no longer open.')]);
        }
        $amount = round(min((float) ($data['amount'] ?? $left), $candidate['open'], $left), 2);
        $rest = $candidate['open'] - $amount >= 0.005 ? ($data['rest'] ?? null) : null;
        if ($candidate['open'] - $amount >= 0.005 && $rest === null) {
            throw ValidationException::withMessages(['rest' => __('Part of it stays open: when is the rest expected?')]);
        }

        return [['t' => 'plan', 'key' => $candidate['item']->key, 'amount' => $amount, 'rest' => $rest], __('Paid a planned item')];
    }

    private function ensureOpen(BankStatement $statement): void
    {
        if (! $statement->isOpen()) {
            throw ValidationException::withMessages(['statement' => __('That statement is concluded.')]);
        }
    }

    /**
     * Uploads and hand conclusions, newest first.
     *
     * @param  Collection<int, Wallet>  $wallets
     * @return list<array<string, mixed>>
     */
    private static function history(Collection $wallets): array
    {
        $names = $wallets->pluck('name', 'id');
        $statements = BankStatement::query()->with('user:id,name', 'concluder:id,name')->withCount('lines')->latest('id')->get()
            ->map(fn (BankStatement $s): array => [
                'key' => "statement-{$s->id}",
                'kind' => 'statement',
                'id' => $s->id,
                'wallet' => $names[$s->wallet_id] ?? '—',
                'from' => $s->from_date->toDateString(),
                'to' => $s->to_date->toDateString(),
                'status' => $s->status,
                'lines' => $s->lines_count,
                'by' => $s->concluder->name ?? $s->user->name ?? null,
                'at' => ($s->concluded_at ?? $s->created_at)->toDateString(),
            ]);
        $byHand = WalletConclusion::query()->with('user:id,name')->whereNull('bank_statement_id')->latest('id')->get()
            ->map(fn (WalletConclusion $c): array => [
                'key' => "hand-{$c->id}",
                'kind' => 'hand',
                'id' => $c->id,
                'wallet' => $names[$c->wallet_id] ?? '—',
                'from' => null,
                'to' => $c->until->toDateString(),
                'status' => 'concluded',
                'lines' => null,
                'by' => $c->user->name ?? null,
                'at' => $c->created_at->toDateString(),
            ]);

        return array_values($statements->concat($byHand)->sortByDesc('at')->all());
    }

    /**
     * A statement as the working view shows it.
     *
     * @return array<string, mixed>
     */
    private static function work(BankStatement $statement): array
    {
        $wallet = $statement->wallet;
        $open = $statement->isOpen();
        $decisions = $open ? StatementDraft::decisions($statement) : [];
        $from = $statement->from_date->toDateString();
        $to = $statement->to_date->toDateString();
        $rows = BankComparison::rows($wallet, $from, $to, $decisions);
        $usedPlan = StatementDraft::usedPlan($decisions);

        $templates = Transaction::query()
            ->with('entity:id,name', 'category:id,name', 'vatLines', 'withheldLines', 'toWallet:id,name')
            ->findMany(collect($rows)->pluck('proposal')->filter(fn ($p) => ($p['kind'] ?? null) === 'like')->pluck('transaction_id'))
            ->keyBy('id');
        $pieceRows = Transaction::query()->with('entity:id,name', 'category:id,name')
            ->findMany(collect($decisions)->flatten(1)->whereIn('t', ['row', 'like'])->pluck('id'))->keyBy('id');
        $lineModels = BankLine::query()->whereIn('id', collect($rows)->pluck('line.id')->filter())->get()->keyBy('id');
        $balance = WalletBalances::all($statement->to_date)[$wallet->id] ?? (float) $wallet->starting_balance;

        return [
            'id' => $statement->id,
            'open' => $open,
            'from' => $from,
            'to' => $to,
            'last_step' => $open ? StatementDraft::lastStep($statement) : null,
            'closing_balance' => $statement->closing_balance !== null ? (float) $statement->closing_balance : null,
            'app_balance' => round($balance, 2),
            'wallet' => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'takes_fees' => $wallet->takesBankFees(),
            ],
            'rows' => array_map(function (array $row) use ($templates, $pieceRows, $usedPlan, $open, $lineModels): array {
                $line = $row['line'];
                $proposal = $row['proposal'];
                $needsPlan = $open && $line !== null && in_array($row['status'], ['bank_only', 'differs', 'partial'], true);
                $item = ($proposal['kind'] ?? null) === 'plan' ? Plan::find($proposal['key']) : null;

                return [
                    'key' => match (true) {
                        $line !== null => "line-{$line->id}",
                        $row['part_of'] !== null => "line-{$row['part_of']->id}-{$row['transaction']?->id}",
                        default => "app-{$row['transaction']?->id}",
                    },
                    'status' => $row['status'],
                    'line' => $line === null ? null : [
                        'id' => $line->id,
                        'date' => $line->date->toDateString(),
                        'text' => $line->text,
                        'amount' => (float) $line->amount,
                    ],
                    'part_of' => $row['part_of']?->id,
                    'transaction' => $row['transaction'] !== null ? self::summary($row['transaction']) : null,
                    'linked' => $row['linked'],
                    'differences' => (object) $row['differences'],
                    'proposal' => $proposal === null ? null : [
                        'kind' => $proposal['kind'],
                        'template' => $proposal['kind'] === 'like' && $templates->has($proposal['transaction_id'])
                            ? self::template($templates[$proposal['transaction_id']])
                            : null,
                        'plan' => $item !== null ? self::planSummary($item, $item->open() - ($usedPlan[$item->key] ?? 0)) : null,
                    ],
                    'decision' => $row['decision'] === null ? null : array_map(
                        fn (array $p): array => self::pieceSummary($p, $pieceRows),
                        $row['decision'],
                    ),
                    'plan_options' => $needsPlan && $lineModels->has($line->id)
                        ? BankReconciler::planCandidates($lineModels[$line->id], $usedPlan)
                            ->sortBy(fn (array $c): int => (int) abs($c['item']->date()->diffInDays($line->date)))
                            ->take(8)
                            ->map(fn (array $c): array => self::planSummary($c['item'], $c['open']))
                            ->values()->all()
                        : [],
                ];
            }, $rows),
        ];
    }

    /**
     * Planned items nobody paid in a concluded period, each with the real rows that
     * could be its payment (same entity, same way, within 60 days, paying nothing).
     *
     * @param  Collection<int, Wallet>  $wallets
     * @return list<array<string, mixed>>
     */
    private static function notMatched(Collection $wallets): array
    {
        $names = $wallets->pluck('name', 'id');

        return array_values(Plan::notMatched()->map(function (PlanItem $i) use ($names): array {
            $t = $i->template;
            $rows = Transaction::query()->with('entity:id,name', 'category:id,name')
                ->where('entity_id', $t->entity_id)
                ->where('type', $t->type)
                ->whereNull('managed_key')
                ->whereBetween('date', [$i->date()->subDays(60)->toDateString(), $i->date()->addDays(60)->toDateString()])
                ->orderBy('date')->limit(8)->get();

            return [
                ...self::planSummary($i, $i->open()),
                'wallet' => $names[$t->wallet_id] ?? '—',
                'candidates' => $rows->map(fn (Transaction $r): array => self::summary($r))->values()->all(),
            ];
        })->all());
    }

    /**
     * @return array<string, mixed>
     */
    private static function planSummary(PlanItem $i, float $open): array
    {
        return [
            'key' => $i->key,
            'kind' => $i->kind,
            'id' => $i->planned()?->id,
            'date' => $i->date()->toDateString(),
            'description' => $i->template->description,
            'entity' => $i->template->entity?->name,
            'type' => $i->template->type,
            'open' => round($open, 2),
            'total' => round($i->total, 2),
        ];
    }

    /**
     * A draft piece in words for the page.
     *
     * @param  array<string, mixed>  $p
     * @param  Collection<int, Transaction>  $rows
     * @return array<string, mixed>
     */
    private static function pieceSummary(array $p, Collection $rows): array
    {
        $row = in_array($p['t'], ['row', 'like'], true) ? $rows->get((int) $p['id']) : null;
        $item = $p['t'] === 'plan' ? Plan::find((string) $p['key']) : null;
        $who = $row !== null ? trim(($row->entity->name ?? '').' · '.($row->category->name ?? ''), ' ·') : '';
        $text = match ($p['t']) {
            'row' => $row !== null ? ($row->description ?: $who) : __('A row'),
            'like' => __('New, like :what', ['what' => $row !== null ? ($row->entity->name ?? $row->description) : __('an earlier row')]),
            'plan' => __('Planned: :what', ['what' => $item !== null ? ($item->template->description ?: ($item->template->entity->name ?? '')) : '']),
            'new' => __('New: :what', ['what' => (($p['data'] ?? [])['description'] ?? '') ?: __('row from the form')]),
            'fee' => __('Bank fee'),
            default => (string) $p['t'],
        };

        return [
            't' => $p['t'],
            'text' => $text,
            'amount' => (float) $p['amount'],
            'rest' => $p['rest'] ?? null,
        ];
    }

    /**
     * What the page shows of a transaction.
     *
     * @return array<string, mixed>
     */
    private static function summary(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'date' => $t->date->toDateString(),
            'type' => $t->type,
            'to_wallet_id' => $t->to_wallet_id,
            'entity' => $t->entity?->name,
            'category' => $t->category?->name,
            'description' => $t->description,
            'amount' => round($t->type === 'transfer' ? (float) $t->net : WalletBalances::cashTotal($t), 2),
            'is_reconciled' => $t->is_reconciled,
        ];
    }

    /**
     * A row a new line would copy: what it shows plus what the add form prefills.
     *
     * @return array<string, mixed>
     */
    private static function template(Transaction $t): array
    {
        $vat = $t->vatLines->sortBy('position')->first();
        $withheld = $t->withheldLines->sortBy('position')->first();

        return [
            ...self::summary($t),
            'to_wallet' => $t->toWallet?->name,
            'wallet_id' => $t->wallet_id,
            'to_wallet_id' => $t->to_wallet_id,
            'entity_id' => $t->entity_id,
            'category_id' => $t->category_id,
            'vat_rate_id' => $vat !== null ? $vat->vat_rate_id : $t->vat_rate_id,
            'withheld_rate_id' => $withheld?->withheld_rate_id,
        ];
    }
}

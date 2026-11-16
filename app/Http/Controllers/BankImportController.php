<?php

namespace App\Http\Controllers;

use App\Models\BankLine;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\AlphaStatement;
use App\Support\BankReconciler;
use App\Support\TaxSync;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Bank import: upload a wallet's bank statement (Alpha Bank CSV), then go
 * through its lines — each one confirms a row the app already has or becomes a new
 * one, so the wallet ends up matching the bank line by line and in the bank's order.
 * The work itself is in {@see BankReconciler}.
 */
class BankImportController extends Controller
{
    /** How far back finished lines are listed (waiting ones always are). */
    private const DONE_DAYS = 400;

    public function index(Request $request): Response
    {
        $wallets = Wallet::query()->orderBy('name')->get();
        $wallet = $wallets->firstWhere('id', (int) $request->input('wallet'))
            ?? $wallets->sortByDesc(fn (Wallet $w) => $w->statement_date?->toDateString() ?? '')->first(fn (Wallet $w) => $w->statement_date !== null)
            ?? $wallets->first(fn (Wallet $w) => $w->takesBankFees())
            ?? $wallets->first();

        $lines = collect();
        $suggestions = [];
        if ($wallet !== null) {
            $lines = BankLine::query()
                ->where('wallet_id', $wallet->id)
                ->where(fn ($q) => $q->whereDate('date', '>=', now()->subDays(self::DONE_DAYS)->toDateString())
                    ->orWhere(fn ($p) => $p->pending()))
                ->with('transaction.entity:id,name', 'transaction.category:id,name')
                ->orderBy('date')->orderBy('position')
                ->get();
            $suggestions = BankReconciler::suggestions($wallet, $lines->filter(fn (BankLine $l) => $l->isPending()));
        }

        $templates = Transaction::query()
            ->with('entity:id,name', 'category:id,name', 'vatLines', 'withheldLines', 'toWallet:id,name')
            ->findMany(collect($suggestions)->pluck('proposal')->filter(fn ($p) => ($p['kind'] ?? null) === 'like')->pluck('transaction_id'))
            ->keyBy('id');

        return Inertia::render('bank-import/index', [
            'wallets' => $wallets->map(fn (Wallet $w): array => ['id' => $w->id, 'name' => $w->name]),
            'wallet' => $wallet === null ? null : [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'takes_fees' => $wallet->takesBankFees(),
                'statement_balance' => $wallet->statement_balance,
                'statement_date' => $wallet->statement_date?->toDateString(),
                // The app's checked balance on the statement's day — equal to the
                // bank's once every line up to then is done.
                'not_in_bank' => BankReconciler::notInBank($wallet),
                'app_balance' => $wallet->statement_date !== null
                    ? (WalletBalances::all($wallet->statement_date)[$wallet->id] ?? (float) $wallet->starting_balance)
                    : null,
            ],
            'lines' => $lines->map(function (BankLine $line) use ($suggestions, $templates): array {
                $s = $suggestions[$line->id] ?? null;
                $proposal = $s['proposal'] ?? null;

                return [
                    'id' => $line->id,
                    'date' => $line->date->toDateString(),
                    'position' => $line->position,
                    'text' => $line->text,
                    'amount' => (float) $line->amount,
                    'status' => $line->isPending() ? 'pending' : ($line->transaction !== null ? 'linked' : 'dismissed'),
                    'transaction' => $line->transaction !== null ? self::summary($line->transaction) : null,
                    'candidates' => array_map(
                        fn (array $c): array => [...self::summary($c['transaction']), 'exact' => $c['exact']],
                        $s['candidates'] ?? [],
                    ),
                    'proposal' => $proposal === null ? null : [
                        'kind' => $proposal['kind'],
                        'transaction_id' => $proposal['transaction_id'] ?? null,
                        'template' => $proposal['kind'] === 'like' && $templates->has($proposal['transaction_id'])
                            ? self::template($templates[$proposal['transaction_id']])
                            : null,
                    ],
                ];
            })->values(),
        ]);
    }

    /** Upload a statement: store its new lines and the balance it shows. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'file' => ['required', 'file', 'max:5120'],
        ]);
        $wallet = Wallet::query()->findOrFail((int) $data['wallet_id']);

        try {
            $statement = AlphaStatement::parse((string) $request->file('file')?->get());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $result = DB::transaction(fn () => BankReconciler::import($wallet, $statement, $request->user()->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} No new lines (:known already imported).|{1} 1 new line (:known already imported).|[2,*] :count new lines (:known already imported).',
            $result['added'],
            ['count' => $result['added'], 'known' => $result['known']],
        )]);

        return redirect()->route('bank-import.index', ['wallet' => $wallet->id]);
    }

    /** Confirm an existing transaction with the line. */
    public function match(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'integer', 'exists:transactions,id']]);
        $this->ensurePending($line);
        $transaction = Transaction::query()->findOrFail((int) $data['transaction_id']);
        if (BankLine::query()->where('transaction_id', $transaction->id)->exists()) {
            throw ValidationException::withMessages(['transaction_id' => __('That transaction is already confirmed by another bank line.')]);
        }

        DB::transaction(function () use ($line, $transaction) {
            BankReconciler::link($line, $transaction);
            TaxSync::run();
        });

        return $this->done(__('Transaction confirmed.'));
    }

    /** Add the line as a bank fee. */
    public function fee(Request $request, BankLine $line): RedirectResponse
    {
        $this->ensurePending($line);
        $wallet = $line->wallet;
        abort_unless($wallet->takesBankFees(), 422);

        DB::transaction(function () use ($line, $wallet, $request) {
            BankReconciler::addFee($line, $wallet, $request->user()->id);
            TaxSync::run();
        });

        return $this->done(__('Bank fee added.'));
    }

    /** Add the line as a new transaction shaped like an earlier one. */
    public function like(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'integer', 'exists:transactions,id']]);
        $this->ensurePending($line);
        $template = Transaction::query()->with('vatLines', 'withheldLines')->findOrFail((int) $data['transaction_id']);

        DB::transaction(function () use ($line, $template, $request) {
            BankReconciler::addLike($line, $template, $request->user()->id);
            TaxSync::run();
        });

        return $this->done(__('Transaction added.'));
    }

    /** Apply the suggestion of each chosen line (those that have one). */
    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:bank_lines,id'],
        ]);

        $applied = DB::transaction(function () use ($data, $request): int {
            $count = 0;
            $lines = BankLine::query()->whereIn('id', $data['ids'])->pending()->get();
            foreach ($lines->groupBy('wallet_id') as $walletLines) {
                $wallet = Wallet::query()->findOrFail($walletLines->first()->wallet_id);
                // Suggestions over all the wallet's waiting lines, so the ones chosen
                // get exactly what the page showed them.
                $all = BankLine::query()->where('wallet_id', $wallet->id)->pending()->get();
                $suggestions = BankReconciler::suggestions($wallet, $all);
                foreach ($walletLines->sortBy(fn (BankLine $l) => $l->date->toDateString().sprintf('%06d', $l->position)) as $line) {
                    $proposal = $suggestions[$line->id]['proposal'] ?? null;
                    if ($proposal === null) {
                        continue;
                    }
                    match ($proposal['kind']) {
                        'match' => BankReconciler::link($line, Transaction::query()->findOrFail($proposal['transaction_id'])),
                        'fee' => BankReconciler::addFee($line, $wallet, $request->user()->id),
                        'like' => BankReconciler::addLike($line, Transaction::query()->with('vatLines', 'withheldLines')->findOrFail($proposal['transaction_id']), $request->user()->id),
                    };
                    $count++;
                }
            }
            TaxSync::run();

            return $count;
        });

        return $this->done(trans_choice(':count line done.|:count lines done.', $applied, ['count' => $applied]));
    }

    /** Set a line aside (handled another way), or bring it back. */
    public function dismiss(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate(['dismissed' => ['required', 'boolean']]);
        if ($data['dismissed']) {
            $this->ensurePending($line);
        }
        $line->update(['is_dismissed' => (bool) $data['dismissed']]);

        return back();
    }

    /**
     * Undo a line's link: it waits again. The transaction stays as it is
     * (reconciled); delete it from its own list if it shouldn't exist.
     */
    public function unlink(BankLine $line): RedirectResponse
    {
        if ($line->transaction_id !== null) {
            Transaction::query()->whereKey($line->transaction_id)->update(['bank_position' => null]);
        }
        $line->update(['transaction_id' => null, 'is_dismissed' => false]);

        return back();
    }

    private function ensurePending(BankLine $line): void
    {
        if (! $line->isPending()) {
            throw ValidationException::withMessages(['line' => __('That bank line is already done.')]);
        }
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
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

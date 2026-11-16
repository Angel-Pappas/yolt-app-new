<?php

namespace App\Http\Controllers;

use App\Models\BankLine;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\AlphaStatement;
use App\Support\BankComparison;
use App\Support\BankReconciler;
use App\Support\TaxSync;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Bank import: upload a wallet's bank statement (Alpha Bank CSV), then see
 * the bank and the app side by side for a period — each statement line next to the
 * row it is, with a verdict (match, differs, missing on one side). Sure pairs and the
 * bank's fees are settled on upload; the rest is accepted, added or paired by hand.
 * The work itself is in {@see BankReconciler} and {@see BankComparison}.
 */
class BankImportController extends Controller
{
    public function index(Request $request): Response
    {
        $wallets = Wallet::query()->orderBy('name')->get();
        $wallet = $wallets->firstWhere('id', (int) $request->input('wallet'))
            ?? $wallets->sortByDesc(fn (Wallet $w) => $w->statement_date?->toDateString() ?? '')->first(fn (Wallet $w) => $w->statement_date !== null)
            ?? $wallets->first(fn (Wallet $w) => $w->takesBankFees())
            ?? $wallets->first();

        [$from, $to] = $this->period($request, $wallet);
        $rows = $wallet !== null ? BankComparison::rows($wallet, $from, $to) : [];

        $templates = Transaction::query()
            ->with('entity:id,name', 'category:id,name', 'vatLines', 'withheldLines', 'toWallet:id,name')
            ->findMany(collect($rows)->pluck('proposal')->filter(fn ($p) => ($p['kind'] ?? null) === 'like')->pluck('transaction_id'))
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
                // bank's once every line up to then is paired.
                'app_balance' => $wallet->statement_date !== null
                    ? (WalletBalances::all($wallet->statement_date)[$wallet->id] ?? (float) $wallet->starting_balance)
                    : null,
            ],
            'period' => ['from' => $from, 'to' => $to],
            'rows' => array_map(function (array $row) use ($templates): array {
                $line = $row['line'];
                $proposal = $row['proposal'];

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
                    // A further row of the line above (one bank line, several rows).
                    'part_of' => $row['part_of']?->id,
                    'transaction' => $row['transaction'] !== null ? self::summary($row['transaction']) : null,
                    'linked' => $row['linked'],
                    'differences' => (object) $row['differences'],
                    'proposal' => $proposal === null ? null : [
                        'kind' => $proposal['kind'],
                        'template' => $proposal['kind'] === 'like' && $templates->has($proposal['transaction_id'])
                            ? self::template($templates[$proposal['transaction_id']])
                            : null,
                    ],
                ];
            }, $rows),
        ]);
    }

    /**
     * The dates shown: as asked, else from the start of the month of the earliest
     * unpaired line (or of the statement's month) to the statement's day.
     *
     * @return array{string, string}
     */
    private function period(Request $request, ?Wallet $wallet): array
    {
        $end = $wallet->statement_date ?? now();
        $earliest = $wallet === null ? null
            : BankLine::query()->where('wallet_id', $wallet->id)->pending()->min('date');
        $start = $earliest !== null ? Carbon::parse($earliest) : $end->copy();

        $from = $this->date($request->input('from')) ?? $start->copy()->startOfMonth()->toDateString();
        $to = $this->date($request->input('to')) ?? $end->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
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

        $result = DB::transaction(function () use ($wallet, $statement, $request): array {
            $result = BankReconciler::import($wallet, $statement, $request->user()->id);
            $result['settled'] = BankReconciler::settle($wallet, $request->user()->id);
            TaxSync::run();

            return $result;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} No new lines|{1} 1 new line|[2,*] :count new lines',
            $result['added'],
            ['count' => $result['added']],
        ).' · '.trans_choice(
            '{0} nothing paired by itself.|{1} 1 paired by itself.|[2,*] :count paired by itself.',
            $result['settled'],
            ['count' => $result['settled']],
        )]);

        return redirect()->route('bank-import.index', ['wallet' => $wallet->id]);
    }

    /**
     * Pair a row with the line by hand (dragged onto it, or chosen from ⋯): it joins
     * the line's rows while they don't add up to the line yet, else takes their
     * place, keeping its own amount ({@see BankReconciler::pair()}).
     */
    public function match(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'integer', 'exists:transactions,id']]);
        $transaction = Transaction::query()->findOrFail((int) $data['transaction_id']);

        DB::transaction(function () use ($line, $transaction) {
            BankReconciler::pair($line, $transaction);
            TaxSync::run();
        });

        return $this->done(__('Paired.'));
    }

    /**
     * Accept a pair: the line's rows become exactly these, on the bank's day — one row
     * also takes the bank's amount; several keep theirs (they add up to the line).
     */
    public function accept(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1', 'max:10'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        DB::transaction(function () use ($line, $data) {
            BankReconciler::accept($line, Transaction::query()->findMany($data['transaction_ids'])->values()->all());
            TaxSync::run();
        });

        return $this->done(__('Accepted — the app now matches the bank.'));
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
                        'group' => BankReconciler::accept($line, Transaction::query()->findMany($proposal['transaction_ids'])->values()->all()),
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

    /**
     * Undo a pair — the whole line, or one row of it. The rows stay as they are
     * (reconciled); delete one if it shouldn't exist.
     */
    public function unlink(Request $request, BankLine $line): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => ['nullable', 'integer']]);
        $only = isset($data['transaction_id']) ? Transaction::query()->find((int) $data['transaction_id']) : null;
        BankReconciler::release($line, $only);

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

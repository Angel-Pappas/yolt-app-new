<?php

namespace App\Http\Controllers;

use App\Models\BankLine;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\AmountLines;
use App\Support\Plan;
use App\Support\StatementDraft;
use App\Support\TransactionWriter;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transactions — the core Finance feature. Available to any active user; shared
 * company data. `type` drives which fields apply: income/expense carry VAT
 * "lines" and optional withholding "lines" (whose per-rate amounts are always
 * computed server-side from the rate at save time — never trusted from the
 * client); a transfer just moves a net amount between two wallets. The cash total
 * of an income/expense is net + VAT − withheld.
 */
class TransactionController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        // Default the view to the current month. A bare visit redirects to this
        // month's range so the URL stays the single source of truth; `all=1` (set by
        // the filter bar's "All time") opts out, as does any explicit from/to.
        if (! $request->filled('from') && ! $request->filled('to')
            && ! $request->filled('invoice_from') && ! $request->filled('invoice_to')
            && ! $request->boolean('all')) {
            return redirect()->route('transactions.index', array_merge($request->query(), [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]));
        }

        // The date range, invoice-date range (Taxes drill-down), quick toggles, and
        // balance view are server-side; search and per-column filtering (type,
        // wallet, category, entity, amounts) happen client-side in the shared list.
        $filters = [
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
            'invoice_from' => $request->filled('invoice_from') ? (string) $request->input('invoice_from') : null,
            'invoice_to' => $request->filled('invoice_to') ? (string) $request->input('invoice_to') : null,
            'unreconciled' => $request->boolean('unreconciled'),
            'no_invoice' => $request->boolean('no_invoice'),
            'all' => $request->boolean('all'),
        ];

        $balanceWallet = $request->filled('balance')
            ? Wallet::query()->find((int) $request->input('balance'), ['id', 'name', 'starting_balance'])
            : null;

        $transactions = $balanceWallet !== null
            ? $this->balanceRows($balanceWallet, $filters)
            : $this->listRows($filters);

        // Deep-link: `?edit=<id>` opens that transaction's edit modal, even when it
        // falls outside the current date filter — so the row's "open in new tab" link
        // (which points here) always works.
        $editing = $request->filled('edit')
            ? Transaction::query()->withListData()->find((int) $request->input('edit'))
            : null;

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'balance' => $balanceWallet !== null
                ? ['wallet_id' => $balanceWallet->id, 'wallet_name' => $balanceWallet->name]
                : null,
            'editing' => $editing,
        ]);
    }

    /**
     * The normal transaction list: filters applied at the database level.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function listRows(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = Transaction::query()->withListData();

        if ($filters['from'] !== null) {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $query->whereDate('date', '<=', $filters['to']);
        }
        if ($filters['invoice_from'] !== null) {
            $query->whereDate('invoice_date', '>=', $filters['invoice_from']);
        }
        if ($filters['invoice_to'] !== null) {
            $query->whereDate('invoice_date', '<=', $filters['invoice_to']);
        }
        if ($filters['unreconciled']) {
            $query->where('is_reconciled', false);
        }
        if ($filters['no_invoice']) {
            // "Not yet worked on" — excludes both a filed month and a not-needed row.
            $query->whereNull('invoice_month')->where('invoice_not_required', false);
        }

        return $query->inBankOrder()->get();
    }

    /**
     * Balance view: one wallet's history with a running balance. The balance is
     * computed over the wallet's complete history first (see WalletBalances), then
     * the display filters are applied in PHP — so a filtered view still shows the
     * correct cumulative balances. The wallet filter is ignored here (the list is
     * already scoped to the balance wallet).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Transaction>
     */
    private function balanceRows(Wallet $wallet, array $filters): Collection
    {
        $rows = WalletBalances::runningFor($wallet->id, (float) $wallet->starting_balance);

        if ($filters['from'] !== null) {
            $rows = $rows->filter(fn (Transaction $t) => substr((string) $t->date, 0, 10) >= $filters['from']);
        }
        if ($filters['to'] !== null) {
            $rows = $rows->filter(fn (Transaction $t) => substr((string) $t->date, 0, 10) <= $filters['to']);
        }
        if ($filters['invoice_from'] !== null) {
            $rows = $rows->filter(fn (Transaction $t) => substr((string) $t->invoice_date, 0, 10) >= $filters['invoice_from']);
        }
        if ($filters['invoice_to'] !== null) {
            $rows = $rows->filter(fn (Transaction $t) => substr((string) $t->invoice_date, 0, 10) <= $filters['invoice_to']);
        }
        if ($filters['unreconciled']) {
            $rows = $rows->filter(fn (Transaction $t) => ! $t->is_reconciled);
        }
        if ($filters['no_invoice']) {
            $rows = $rows->filter(fn (Transaction $t) => $t->invoice_month === null && ! $t->invoice_not_required);
        }

        return $rows->values();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTransaction($request);
        $extra = $request->validate([
            // Added from a bank statement line: the new row confirms that line.
            'bank_line_id' => ['nullable', 'integer', 'exists:bank_lines,id'],
            // A bank fee paid with it, added as its own row (see addBankFee()).
            'bank_fee' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $line = isset($extra['bank_line_id']) ? BankLine::query()->with('statement')->findOrFail((int) $extra['bank_line_id']) : null;
        if ($line !== null) {
            // From a bank line of an open statement: a decision of its draft — the row
            // is only added when the statement is concluded.
            if ($line->statement === null || ! $line->statement->isOpen()) {
                throw ValidationException::withMessages(['bank_line_id' => __('That bank line is already done.')]);
            }
            $fee = isset($extra['bank_fee']) ? round((float) $extra['bank_fee'], 2) : null;
            StatementDraft::addPiece($line->statement, $line, [
                't' => 'new',
                'data' => $data,
                'fee' => $fee,
                'amount' => StatementDraft::cashOf($data, $fee),
            ], __('Added a row'), $request->user()->id);

            return back();
        }

        DB::transaction(function () use ($data, $extra, $request) {
            $transaction = new Transaction;
            $transaction->user_id = $request->user()->id;
            TransactionWriter::persist($transaction, $data);
            self::paysPlanItem($transaction, $data);
            if (isset($extra['bank_fee'])) {
                $this->addBankFee($transaction, round((float) $extra['bank_fee'], 2), $request->user()->id);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction added.')]);

        return back();
    }

    /**
     * Tie a real row to the plan item it pays (or untie it): it carries the item's
     * key, and a recurrence's or a tax's row says where it came from. A key the plan
     * doesn't know is refused — unless the row already carried it.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function paysPlanItem(Transaction $transaction, array $data): void
    {
        if (! array_key_exists('managed_key', $data)) {
            return;
        }
        $key = $data['managed_key'] ?: null;
        if ($key === $transaction->managed_key) {
            return;
        }
        if ($key !== null && Plan::find($key) === null) {
            throw ValidationException::withMessages(['managed_key' => __('That planned item is no longer open.')]);
        }

        $item = $key !== null ? Plan::find($key) : null;
        $transaction->forceFill([
            'managed_key' => $key,
            'source' => match ($item?->kind) {
                'recurrence' => 'recurrence',
                'tax' => 'tax',
                default => null,
            },
            'recurrence_id' => $item?->template->recurrence_id,
        ])->save();
    }

    /**
     * The bank's fee for a payment, as its own expense on the same wallet and day —
     * to the entity and category the wallet's fees go to. Ignored for a wallet that
     * has none set.
     */
    private function addBankFee(Transaction $payment, float $fee, int $userId): ?Transaction
    {
        $wallet = Wallet::query()->find($payment->wallet_id);
        if ($wallet === null || ! $wallet->takesBankFees()) {
            return null;
        }

        $row = new Transaction;
        $row->user_id = $userId;
        TransactionWriter::persist($row, [
            'type' => 'expense',
            'date' => $payment->date->toDateString(),
            'invoice_date' => $payment->date->toDateString(),
            'description' => '',
            'wallet_id' => $wallet->id,
            'entity_id' => $wallet->fee_entity_id,
            'category_id' => $wallet->fee_category_id,
            'amount_mode' => 'total',
            'lines' => [['amount' => $fee, 'vat_rate_id' => null]],
        ]);

        return $row;
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $this->validateTransaction($request);

        DB::transaction(function () use ($transaction, $data) {
            TransactionWriter::persist($transaction, $data);
            self::paysPlanItem($transaction, $data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction updated.')]);

        return back();
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction) {
            $transaction->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction deleted.')]);

        return back();
    }

    /**
     * Move a set of transactions to a different category — the "transfer selected"
     * bulk action on a category's page. Only transactions whose type matches the
     * target category's type are moved (income can't take an expense category), so a
     * stray mismatched id is a safe no-op rather than a broken row.
     */
    public function bulkCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:transactions,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $category = Category::query()->where('id', $data['category_id'])->firstOrFail();

        $moved = Transaction::query()
            ->whereIn('id', $data['ids'])
            ->where('type', $category->type)
            ->update(['category_id' => $category->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count transaction moved to :name.|:count transactions moved to :name.', $moved, ['count' => $moved, 'name' => $category->name]),
        ]);

        return back();
    }

    /**
     * Soft-delete a set of transactions — the "delete selected" bulk action.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $deleted = Transaction::query()->whereIn('id', $data['ids'])->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count transaction deleted.|:count transactions deleted.', (int) $deleted, ['count' => $deleted]),
        ]);

        return back();
    }

    /**
     * Reconcile a transaction: a reduced edit of just the fields that drift when a
     * (future/projected) transaction actually happens — date, amount, and wallet(s)
     * — plus the reconciled flag. Reconciling is itself the record that the user
     * checked the row, whether or not anything changed. When the amount changes on
     * an income/expense, its VAT lines are rescaled proportionally and their VAT
     * re-derived from each line's rate, so the breakdown never goes stale.
     */
    public function reconcile(Request $request, Transaction $transaction): RedirectResponse
    {
        $rules = [
            'date' => ['required', 'date'],
            'net' => ['required', 'numeric', 'min:0'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'is_reconciled' => ['boolean'],
        ];
        if ($transaction->type === 'transfer') {
            $rules['to_wallet_id'] = ['required', 'integer', 'different:wallet_id', 'exists:wallets,id'];
        }
        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $transaction) {
            $newNet = round((float) $data['net'], 2);
            $reconciled = $data['is_reconciled'] ?? true;

            // The invoice date follows the payment date until it has been set apart
            // by hand — so it moves along here only while the two are still equal.
            $followsDate = $transaction->invoice_date->toDateString() === $transaction->date->toDateString();

            $transaction->fill([
                'date' => $data['date'],
                'wallet_id' => $data['wallet_id'],
                'is_reconciled' => $reconciled,
            ]);
            if ($followsDate) {
                $transaction->invoice_date = $data['date'];
            }
            // A reconciled row really happened, so it always counts.
            if ($reconciled) {
                $transaction->is_excluded = false;
            }

            if ($transaction->type === 'transfer') {
                $transaction->to_wallet_id = $data['to_wallet_id'];
                $transaction->net = number_format($newNet, 2, '.', '');
            } else {
                TransactionWriter::rescaleLines($transaction, $newNet);
            }

            $transaction->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction reconciled.')]);

        return back();
    }

    /**
     * Set a transaction's invoice-filing state from a single 1–13 input: 1–12 files
     * it under that month's folder; 13 marks it as needing no invoice; anything else
     * (blank) clears both back to unreviewed. Keeping one input means the rest of the
     * app never has to know about the "13" convention.
     */
    public function invoice(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,13'],
        ]);
        // Cast to int: validated data keeps the raw request value (a string like
        // "13"), so the strict comparisons below (=== 13) would never match.
        $input = isset($data['month']) ? (int) $data['month'] : null;

        $transaction->update([
            'invoice_month' => $input !== null && $input <= 12 ? $input : null,
            'invoice_not_required' => $input === 13,
        ]);

        return back();
    }

    /**
     * Update just a transaction's description — the ⓘ description editor on the
     * transactions table. A dedicated partial update so it needn't resubmit the whole
     * transaction. The column is NOT NULL, so a cleared description stores "".
     */
    public function describe(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction->update(['description' => $data['description'] ?? '']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Description updated.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTransaction(Request $request): array
    {
        $rules = [
            'type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'date' => ['required', 'date'],
            'invoice_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            // The plan item this real row pays ("Pays planned item"); absent = leave.
            'managed_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        if ($request->input('type') === 'transfer') {
            $rules['to_wallet_id'] = ['required', 'integer', 'different:wallet_id', 'exists:wallets,id'];
            $rules['net'] = ['required', 'numeric', 'min:0'];
        } else {
            $rules['entity_id'] = ['nullable', 'integer', 'exists:entities,id'];
            $rules['category_id'] = ['nullable', 'integer', 'exists:categories,id'];

            if ($this->isPayrollRequest($request)) {
                // Payroll: a plain net plus manual FMY/EFKA amounts (no VAT lines).
                $rules['net'] = ['required', 'numeric', 'min:0'];
                $rules['fmy_amount'] = ['nullable', 'numeric', 'min:0'];
                $rules['efka_employee_amount'] = ['nullable', 'numeric', 'min:0'];
                $rules['efka_employer_amount'] = ['nullable', 'numeric', 'min:0'];
            } else {
                $rules['amount_mode'] = ['required', 'in:net,total'];
                $rules['lines'] = ['required', 'array', 'min:1'];
                $rules['lines.*.amount'] = ['required', 'numeric', 'min:0'];
                $rules['lines.*.vat_rate_id'] = ['nullable', 'integer', 'exists:vat_rates,id'];
                // Withholding is a per-line option now: a line carries a withheld rate
                // and its base is the line's own net (computed server-side).
                $rules['lines.*.withheld_rate_id'] = ['nullable', 'integer', 'exists:withheld_tax_rates,id'];
            }
        }

        return $request->validate($rules);
    }

    /** Whether the request's selected category is the payroll category. */
    protected function isPayrollRequest(Request $request): bool
    {
        $categoryId = $request->input('category_id');

        return $categoryId !== null && $this->isPayrollCategory((int) $categoryId);
    }

    protected function isPayrollCategory(int $categoryId): bool
    {
        return AmountLines::isPayrollCategory($categoryId);
    }

    /**
     * Switch a transaction off or on. Off, it stays in every list but counts for
     * nothing. A reconciled row really happened and a tax row is derived, so neither
     * can be switched off.
     */
    public function exclude(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate(['excluded' => ['required', 'boolean']]);
        $excluded = (bool) $data['excluded'];

        if ($excluded && ($transaction->is_reconciled || $transaction->source === 'tax')) {
            throw ValidationException::withMessages([
                'excluded' => $transaction->is_reconciled
                    ? __('A reconciled transaction cannot be excluded.')
                    : __('A tax payment is calculated and cannot be excluded.'),
            ]);
        }

        DB::transaction(function () use ($transaction, $excluded) {
            $transaction->update(['is_excluded' => $excluded]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $excluded ? __('Transaction excluded.') : __('Transaction included.')]);

        return back();
    }
}

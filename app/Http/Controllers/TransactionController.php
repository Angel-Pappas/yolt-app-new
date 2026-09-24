<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use App\Support\AmountLines;
use App\Support\TaxSync;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        return $query->orderBy('date')->orderBy('id')->get();
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

        DB::transaction(function () use ($data, $request) {
            $transaction = new Transaction;
            $transaction->user_id = $request->user()->id;
            $this->persist($transaction, $data);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction added.')]);

        return back();
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $this->validateTransaction($request);

        DB::transaction(function () use ($transaction, $data) {
            $this->persist($transaction, $data);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction updated.')]);

        return back();
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction) {
            $transaction->delete();
            TaxSync::run();
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

        TaxSync::run();

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

        TaxSync::run();

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

            $transaction->fill([
                'date' => $data['date'],
                'wallet_id' => $data['wallet_id'],
                'is_reconciled' => $data['is_reconciled'] ?? true,
            ]);

            if ($transaction->type === 'transfer') {
                $transaction->to_wallet_id = $data['to_wallet_id'];
                $transaction->net = number_format($newNet, 2, '.', '');
            } else {
                $this->rescaleLines($transaction, $newNet);
            }

            $transaction->save();
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction reconciled.')]);

        return back();
    }

    /**
     * Distribute a new net across a transaction's VAT lines in proportion to their
     * current share (the last line absorbs any rounding remainder so the parts sum
     * exactly), re-deriving each line's VAT from its rate. A withheld line stays
     * coupled to its VAT line (matched by position): its base is reset to the line's
     * new net and its withheld amount re-derived from its rate. The summed
     * net/vat_amount/withheld_amount on the transaction are updated to match.
     */
    private function rescaleLines(Transaction $transaction, float $newNet): void
    {
        $lines = $transaction->vatLines()->orderBy('position')->get();
        if ($lines->isEmpty()) {
            $transaction->net = number_format($newNet, 2, '.', '');

            return;
        }

        $withheldByPosition = $transaction->withheldLines()->get()->keyBy('position');
        $oldNet = (float) $lines->sum(fn ($l) => (float) $l->net);
        $vatRates = VatRate::query()
            ->whereIn('id', $lines->pluck('vat_rate_id')->filter())
            ->pluck('rate', 'id');
        $withheldRates = WithheldTaxRate::query()
            ->whereIn('id', $withheldByPosition->pluck('withheld_rate_id')->filter())
            ->pluck('rate', 'id');

        $count = $lines->count();
        $remaining = $newNet;
        $sumNet = 0.0;
        $sumVat = 0.0;
        $sumWithheld = 0.0;

        foreach ($lines->values() as $i => $line) {
            if ($i === $count - 1) {
                $lineNet = round($remaining, 2);
            } elseif ($oldNet > 0) {
                $lineNet = round($newNet * (float) $line->net / $oldNet, 2);
            } else {
                $lineNet = $i === 0 ? $newNet : 0.0;
            }
            $remaining -= $lineNet;

            $rate = $line->vat_rate_id !== null ? (float) $vatRates[$line->vat_rate_id] : 0.0;
            $lineVat = round($lineNet * $rate / 100, 2);
            $line->update(['net' => $lineNet, 'vat_amount' => $lineVat]);

            $sumNet += $lineNet;
            $sumVat += $lineVat;

            $withheldLine = $withheldByPosition->get($line->position);
            if ($withheldLine !== null) {
                $wRate = $withheldLine->withheld_rate_id !== null
                    ? (float) $withheldRates[$withheldLine->withheld_rate_id]
                    : 0.0;
                $lineWithheld = round($lineNet * $wRate / 100, 2);
                $withheldLine->update(['net' => $lineNet, 'withheld_amount' => $lineWithheld]);
                $sumWithheld += $lineWithheld;
            }
        }

        $transaction->net = number_format($sumNet, 2, '.', '');
        $transaction->vat_amount = number_format($sumVat, 2, '.', '');
        $transaction->withheld_amount = number_format($sumWithheld, 2, '.', '');
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
    private function validateTransaction(Request $request): array
    {
        $rules = [
            'type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'date' => ['required', 'date'],
            'invoice_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
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
    private function isPayrollRequest(Request $request): bool
    {
        $categoryId = $request->input('category_id');

        return $categoryId !== null && $this->isPayrollCategory((int) $categoryId);
    }

    private function isPayrollCategory(int $categoryId): bool
    {
        return AmountLines::isPayrollCategory($categoryId);
    }

    /**
     * A payroll amount from validated data: the rounded number, or null when blank.
     *
     * @param  array<string, mixed>  $data
     */
    private static function payrollAmount(array $data, string $key): ?float
    {
        return isset($data[$key]) ? round((float) $data[$key], 2) : null;
    }

    /**
     * Set a transaction's fields from validated input and (re)write its VAT and
     * withholding lines wholesale. Shared by store and update; handles both a
     * transfer and an income/expense, nulling the other shape's fields so a type
     * change on edit leaves no stale data behind.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(Transaction $transaction, array $data): void
    {
        if ($data['type'] === 'transfer') {
            $transaction->fill([
                'type' => 'transfer',
                'date' => $data['date'],
                'invoice_date' => $data['invoice_date'],
                'description' => $data['description'] ?? '',
                'wallet_id' => $data['wallet_id'],
                'to_wallet_id' => $data['to_wallet_id'],
                'entity_id' => null,
                'category_id' => null,
                'vat_rate_id' => null,
                'net' => round((float) $data['net'], 2),
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'fmy_amount' => null,
                'efka_employee_amount' => null,
                'efka_employer_amount' => null,
            ]);
            $transaction->save();
            $transaction->vatLines()->delete();
            $transaction->withheldLines()->delete();

            return;
        }

        if (isset($data['category_id']) && $this->isPayrollCategory((int) $data['category_id'])) {
            // Payroll: store the net (gross) plus the manual FMY/EFKA amounts. VAT and
            // withholding are always zero here; the cash the wallet moves is the "To
            // Pay" = net − FMY − employee EFKA, derived by WalletBalances::cashTotal.
            $transaction->fill([
                'type' => $data['type'],
                'date' => $data['date'],
                'invoice_date' => $data['invoice_date'],
                'description' => $data['description'] ?? '',
                'entity_id' => $data['entity_id'] ?? null,
                'category_id' => $data['category_id'],
                'wallet_id' => $data['wallet_id'],
                'to_wallet_id' => null,
                'vat_rate_id' => null,
                'net' => round((float) $data['net'], 2),
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'fmy_amount' => self::payrollAmount($data, 'fmy_amount'),
                'efka_employee_amount' => self::payrollAmount($data, 'efka_employee_amount'),
                'efka_employer_amount' => self::payrollAmount($data, 'efka_employer_amount'),
            ]);
            $transaction->save();
            $transaction->vatLines()->delete();
            $transaction->withheldLines()->delete();

            return;
        }

        $resolved = AmountLines::resolve($data['lines'], $data['amount_mode']);

        $transaction->fill([
            'type' => $data['type'],
            'date' => $data['date'],
            'invoice_date' => $data['invoice_date'],
            'description' => $data['description'] ?? '',
            'entity_id' => $data['entity_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'wallet_id' => $data['wallet_id'],
            'to_wallet_id' => null,
            'net' => $resolved['net'],
            'vat_amount' => $resolved['vat_amount'],
            'withheld_amount' => $resolved['withheld_amount'],
            'fmy_amount' => null,
            'efka_employee_amount' => null,
            'efka_employer_amount' => null,
            // A single line keeps the (denormalized) rate; mixed rates = null.
            'vat_rate_id' => count($resolved['vat_lines']) === 1
                ? $resolved['vat_lines'][0]['vat_rate_id']
                : null,
        ]);
        $transaction->save();

        $transaction->vatLines()->delete();
        $transaction->vatLines()->createMany($resolved['vat_lines']);
        $transaction->withheldLines()->delete();
        $transaction->withheldLines()->createMany($resolved['withheld_lines']);
    }
}

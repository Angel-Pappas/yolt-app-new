<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
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

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'balance' => $balanceWallet !== null
                ? ['wallet_id' => $balanceWallet->id, 'wallet_name' => $balanceWallet->name]
                : null,
            'wallets' => Wallet::query()->orderBy('name')->get(['id', 'name']),
            'entities' => Entity::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
            'vatRates' => VatRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
            'withheldRates' => WithheldTaxRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
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
        $query = Transaction::query()->with([
            'wallet:id,name',
            'toWallet:id,name',
            'entity:id,name',
            'category:id,name',
            'vatLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'vat_rate_id', 'position'),
            'withheldLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'withheld_rate_id', 'position'),
        ]);

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
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction added.')]);

        return back();
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $this->validateTransaction($request);

        DB::transaction(fn () => $this->persist($transaction, $data));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction updated.')]);

        return back();
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

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
            $rules['amount_mode'] = ['required', 'in:net,total'];
            $rules['lines'] = ['required', 'array', 'min:1'];
            $rules['lines.*.amount'] = ['required', 'numeric', 'min:0'];
            $rules['lines.*.vat_rate_id'] = ['nullable', 'integer', 'exists:vat_rates,id'];
            // Withholding is a per-line option now: a line carries a withheld rate
            // and its base is the line's own net (computed server-side).
            $rules['lines.*.withheld_rate_id'] = ['nullable', 'integer', 'exists:withheld_tax_rates,id'];
        }

        return $request->validate($rules);
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
            ]);
            $transaction->save();
            $transaction->vatLines()->delete();
            $transaction->withheldLines()->delete();

            return;
        }

        $resolved = $this->resolveLines($data['lines'], $data['amount_mode']);

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

    /**
     * Resolve each amount line into a VAT line and — when the line carries a
     * withholding rate — a withheld line whose base is that same line's net, all
     * computed server-side from the rates' current percentages (never trusted from
     * the client). The single Net/Total mode governs the whole transaction:
     *
     *  - "net": the typed amount is the line's net. VAT = net × v, withheld = net × w.
     *  - "total": the typed amount is the cash total (net + VAT − withheld). The net
     *    is reversed out as total ÷ (1 + (v − w)/100); withheld = net × w; and VAT is
     *    anchored to (total − net + withheld) so the line reconstructs to the exact
     *    typed total without double-rounding drift.
     *
     * The withheld line shares its VAT line's `position`, so the two can be
     * re-coupled when the transaction is loaded back into the form.
     *
     * @param  array<int, array{amount: mixed, vat_rate_id?: mixed, withheld_rate_id?: mixed}>  $lines
     * @return array{net: float, vat_amount: float, withheld_amount: float, vat_lines: array<int, array{net: float, vat_rate_id: int|null, vat_amount: float, position: int}>, withheld_lines: array<int, array{net: float, withheld_rate_id: int, withheld_amount: float, position: int}>}
     */
    private function resolveLines(array $lines, string $mode): array
    {
        $vatRates = VatRate::query()
            ->whereIn('id', collect($lines)->pluck('vat_rate_id')->filter()->all())
            ->pluck('rate', 'id');
        $withheldRates = WithheldTaxRate::query()
            ->whereIn('id', collect($lines)->pluck('withheld_rate_id')->filter()->all())
            ->pluck('rate', 'id');

        $net = 0.0;
        $vatAmount = 0.0;
        $withheldAmount = 0.0;
        $vatLines = [];
        $withheldLines = [];

        foreach (array_values($lines) as $i => $line) {
            $amount = round((float) $line['amount'], 2);
            $vatRateId = ($line['vat_rate_id'] ?? null) ? (int) $line['vat_rate_id'] : null;
            $withheldRateId = ($line['withheld_rate_id'] ?? null) ? (int) $line['withheld_rate_id'] : null;
            $v = $vatRateId !== null ? (float) $vatRates[$vatRateId] : 0.0;
            $w = $withheldRateId !== null ? (float) $withheldRates[$withheldRateId] : 0.0;

            if ($mode === 'total') {
                $denom = 1 + ($v - $w) / 100;
                $lineNet = $denom > 0 ? round($amount / $denom, 2) : $amount;
                $lineWithheld = round($lineNet * $w / 100, 2);
                $lineVat = round($amount - $lineNet + $lineWithheld, 2);
            } else {
                $lineNet = $amount;
                $lineVat = round($lineNet * $v / 100, 2);
                $lineWithheld = round($lineNet * $w / 100, 2);
            }

            $net += $lineNet;
            $vatAmount += $lineVat;
            $withheldAmount += $lineWithheld;

            $vatLines[] = [
                'net' => $lineNet,
                'vat_rate_id' => $vatRateId,
                'vat_amount' => $lineVat,
                'position' => $i,
            ];

            if ($withheldRateId !== null) {
                $withheldLines[] = [
                    'net' => $lineNet,
                    'withheld_rate_id' => $withheldRateId,
                    'withheld_amount' => $lineWithheld,
                    'position' => $i,
                ];
            }
        }

        return [
            'net' => round($net, 2),
            'vat_amount' => round($vatAmount, 2),
            'withheld_amount' => round($withheldAmount, 2),
            'vat_lines' => $vatLines,
            'withheld_lines' => $withheldLines,
        ];
    }
}

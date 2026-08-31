<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transactions — the core Finance feature. Gated by `can:access-finance`; shared
 * company data. `type` drives which fields apply: income/expense carry VAT
 * "lines" (whose per-rate VAT is always computed server-side from the rate at
 * save time — never trusted from the client); a transfer just moves a net amount
 * from one wallet to another with no VAT/entity/category. Multi-line, withholding,
 * and filtering come in later slices.
 */
class TransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('transactions/index', [
            'transactions' => Transaction::query()
                ->with([
                    'wallet:id,name',
                    'toWallet:id,name',
                    'entity:id,name',
                    'category:id,name',
                ])
                ->orderBy('date')
                ->orderBy('id')
                ->get([
                    'id', 'date', 'invoice_date', 'description', 'type',
                    'net', 'vat_amount', 'withheld_amount', 'wallet_id',
                    'to_wallet_id', 'entity_id', 'category_id', 'vat_rate_id',
                    'is_reconciled',
                ]),
            'wallets' => Wallet::query()->orderBy('name')->get(['id', 'name']),
            'entities' => Entity::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
            'vatRates' => VatRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
        ]);
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
            $rules['lines'] = ['required', 'array', 'min:1'];
            $rules['lines.*.net'] = ['required', 'numeric', 'min:0'];
            $rules['lines.*.vat_rate_id'] = ['nullable', 'integer', 'exists:vat_rates,id'];
        }

        return $request->validate($rules);
    }

    /**
     * Set a transaction's fields from validated input and (re)write its VAT
     * lines wholesale. Shared by store and update; handles both a transfer and
     * an income/expense. Fields for the other shape are nulled so a type change
     * on edit leaves no stale data behind.
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

            return;
        }

        $resolved = $this->resolveLines($data['lines']);

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
            // A single line keeps the (denormalized) rate; mixed rates = null.
            'vat_rate_id' => count($resolved['lines']) === 1
                ? $resolved['lines'][0]['vat_rate_id']
                : null,
        ]);
        $transaction->save();

        $transaction->vatLines()->delete();
        $transaction->vatLines()->createMany($resolved['lines']);
    }

    /**
     * Compute each line's VAT from the rate's current percentage (never trusted
     * from the client) and the summed net / vat_amount across all lines.
     *
     * @param  array<int, array{net: mixed, vat_rate_id?: mixed}>  $lines
     * @return array{net: float, vat_amount: float, lines: array<int, array{net: float, vat_rate_id: int|null, vat_amount: float, position: int}>}
     */
    private function resolveLines(array $lines): array
    {
        $rateIds = collect($lines)
            ->pluck('vat_rate_id')
            ->filter()
            ->all();
        $rates = VatRate::query()
            ->whereIn('id', $rateIds)
            ->pluck('rate', 'id');

        $net = 0.0;
        $vatAmount = 0.0;
        $resolved = [];

        foreach (array_values($lines) as $i => $line) {
            $lineNet = round((float) $line['net'], 2);
            $rateId = ($line['vat_rate_id'] ?? null) ? (int) $line['vat_rate_id'] : null;
            $rate = $rateId !== null ? (float) $rates[$rateId] : 0.0;
            $lineVat = round($lineNet * $rate / 100, 2);

            $net += $lineNet;
            $vatAmount += $lineVat;

            $resolved[] = [
                'net' => $lineNet,
                'vat_rate_id' => $rateId,
                'vat_amount' => $lineVat,
                'position' => $i,
            ];
        }

        return [
            'net' => round($net, 2),
            'vat_amount' => round($vatAmount, 2),
            'lines' => $resolved,
        ];
    }
}

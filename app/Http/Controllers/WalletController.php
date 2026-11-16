<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wallets CRUD. Available to any active user. Wallets are shared
 * company data; `user_id` records who created a wallet (audit only). The list
 * shows each wallet's current balance (derived live — see WalletBalances).
 */
class WalletController extends Controller
{
    public function index(): Response
    {
        $balances = WalletBalances::all();

        return Inertia::render('wallets/index', [
            'wallets' => Wallet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'starting_balance', 'fee_entity_id', 'fee_category_id'])
                ->map(fn (Wallet $wallet): array => [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'starting_balance' => $wallet->starting_balance,
                    'fee_entity_id' => $wallet->fee_entity_id,
                    'fee_category_id' => $wallet->fee_category_id,
                    'balance' => $balances[$wallet->id] ?? (float) $wallet->starting_balance,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wallet = new Wallet($this->validateWallet($request));
        $wallet->user_id = $request->user()->id;
        $wallet->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wallet created.')]);

        return back();
    }

    public function update(Request $request, Wallet $wallet): RedirectResponse
    {
        $wallet->update($this->validateWallet($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wallet updated.')]);

        return back();
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        $wallet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Wallet deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWallet(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starting_balance' => ['required', 'numeric'],
            // Where this wallet's bank fees go; both set = the transaction form
            // offers "Bank fee" and the Bank import adds fee lines.
            'fee_entity_id' => ['nullable', 'integer', 'exists:entities,id', 'required_with:fee_category_id'],
            'fee_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'expense'), 'required_with:fee_entity_id'],
        ]);
    }
}

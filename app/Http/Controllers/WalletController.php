<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Support\WalletBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wallets CRUD. Gated by `can:access-finance` on the routes. Wallets are shared
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
                ->get(['id', 'name', 'starting_balance'])
                ->map(fn (Wallet $wallet): array => [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'starting_balance' => $wallet->starting_balance,
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
        ]);
    }
}

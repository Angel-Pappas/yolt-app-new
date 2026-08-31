<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Collection;

/**
 * Current wallet balances, derived live (never stored, so they can't drift):
 * each wallet's `starting_balance` plus every active transaction's effect.
 * Income/expense move the cash total (net + VAT − withheld); a transfer moves
 * net out of `wallet_id` and into `to_wallet_id`.
 */
class WalletBalances
{
    /**
     * The complete chronological history of a single wallet, each transaction
     * annotated with a `balance` attribute = the running balance after it (seeded
     * from the wallet's starting balance). Because a running balance depends on
     * every prior row, this always walks the wallet's full history; display
     * filters are applied to the result afterwards, not to this walk.
     *
     * @return Collection<int, Transaction>
     */
    public static function runningFor(int $walletId, float $startingBalance): Collection
    {
        $running = $startingBalance;

        return Transaction::query()
            ->with([
                'wallet:id,name',
                'toWallet:id,name',
                'entity:id,name',
                'category:id,name',
                'withheldLines:id,transaction_id,net,withheld_rate_id',
            ])
            ->where(fn ($q) => $q->where('wallet_id', $walletId)->orWhere('to_wallet_id', $walletId))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (Transaction $t) use (&$running, $walletId): void {
                $total = (float) $t->net + (float) $t->vat_amount - (float) $t->withheld_amount;

                if ($t->type === 'income' && $t->wallet_id === $walletId) {
                    $running += $total;
                } elseif ($t->type === 'expense' && $t->wallet_id === $walletId) {
                    $running -= $total;
                } elseif ($t->type === 'transfer') {
                    if ($t->wallet_id === $walletId) {
                        $running -= (float) $t->net;
                    }
                    if ($t->to_wallet_id === $walletId) {
                        $running += (float) $t->net;
                    }
                }

                $t->setAttribute('balance', round($running, 2));
            });
    }

    /**
     * @return array<int, float> wallet id => current balance
     */
    public static function all(): array
    {
        $balances = [];
        foreach (Wallet::query()->get(['id', 'starting_balance']) as $wallet) {
            $balances[$wallet->id] = (float) $wallet->starting_balance;
        }

        Transaction::query()
            ->get(['type', 'net', 'vat_amount', 'withheld_amount', 'wallet_id', 'to_wallet_id'])
            ->each(function (Transaction $t) use (&$balances): void {
                $total = (float) $t->net + (float) $t->vat_amount - (float) $t->withheld_amount;

                if ($t->type === 'income') {
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) + $total;
                } elseif ($t->type === 'expense') {
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) - $total;
                } elseif ($t->type === 'transfer') {
                    $net = (float) $t->net;
                    $balances[$t->wallet_id] = ($balances[$t->wallet_id] ?? 0.0) - $net;
                    if ($t->to_wallet_id !== null) {
                        $balances[$t->to_wallet_id] = ($balances[$t->to_wallet_id] ?? 0.0) + $net;
                    }
                }
            });

        return array_map(fn (float $b): float => round($b, 2), $balances);
    }
}

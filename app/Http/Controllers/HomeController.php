<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\Projection;
use App\Support\WalletBalances;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: what needs attention today, each figure linking to the page behind it —
 * the money really in the wallets, income due and overdue, taxes to pay this month,
 * rows waiting to be reconciled, agreements about to end, and the year ahead.
 */
class HomeController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $balances = WalletBalances::all();
        $wallets = Wallet::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Wallet $w): array => ['id' => $w->id, 'name' => $w->name, 'balance' => $balances[$w->id] ?? 0.0]);

        // Income still open (agreed or invoiced) that counts.
        $openIncome = Transaction::query()->counted()
            ->where('type', 'income')
            ->whereIn('income_status', ['agreed', 'invoiced'])
            ->whereDate('date', '<=', $monthEnd)
            ->get(['date', 'net', 'vat_amount', 'withheld_amount', 'fmy_amount', 'efka_employee_amount']);
        $overdue = $openIncome->filter(fn (Transaction $t) => $t->date->toDateString() < $today->toDateString());
        $thisMonth = $openIncome->filter(fn (Transaction $t) => $t->date->toDateString() >= $monthStart);

        $taxes = Transaction::query()
            ->where('source', 'tax')
            ->where('is_reconciled', false)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->get(['net']);

        return Inertia::render('home', [
            'wallets' => $wallets,
            'income' => [
                'overdue' => self::tally($overdue),
                'this_month' => self::tally($thisMonth),
            ],
            'taxes_this_month' => round((float) $taxes->sum(fn (Transaction $t) => (float) $t->net), 2),
            'to_reconcile' => Transaction::query()
                ->where('is_reconciled', false)
                ->where('is_excluded', false)
                ->whereDate('date', '<', $today->toDateString())
                ->count(),
            'agreements_ending' => array_values(collect(AgreementController::rows(Agreement::query()))
                ->where('state', 'ending')
                ->map(fn (array $a): array => collect($a)->only(['id', 'title', 'entity', 'end_date', 'days_left'])->all())
                ->all()),
            'projection' => Projection::monthly(
                $today->copy()->startOfMonth()->format('Y-m'),
                $today->copy()->startOfMonth()->addMonths(11)->format('Y-m'),
            ),
            'current_month' => $today->format('Y-m'),
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $rows
     * @return array{count: int, total: float}
     */
    private static function tally(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'total' => round((float) $rows->sum(fn (Transaction $t) => WalletBalances::cashTotal($t)), 2),
        ];
    }
}

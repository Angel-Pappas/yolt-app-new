<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Wallet;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\Projection;
use App\Support\WalletBalances;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: what needs attention today, each figure linking to the page behind it —
 * the money really in the wallets, customer payments due and overdue, taxes to pay
 * this month, planned items nothing has matched in a concluded period, agreements
 * about to end, and the year ahead. Income and taxes come from the plan.
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

        // Customer payment lines still owed (switched on), as planned.
        $open = Plan::items()->filter(fn (PlanItem $i): bool => ! $i->isDone() && ! $i->isExcluded());
        $owed = $open->filter(fn (PlanItem $i): bool => $i->kind === 'payment' && $i->date()->toDateString() <= $monthEnd);
        $overdue = $owed->filter(fn (PlanItem $i): bool => $i->date()->toDateString() < $monthStart);
        $thisMonth = $owed->filter(fn (PlanItem $i): bool => $i->date()->toDateString() >= $monthStart);
        $taxes = $open->filter(fn (PlanItem $i): bool => $i->kind === 'tax'
            && $i->date()->toDateString() >= $monthStart && $i->date()->toDateString() <= $monthEnd);

        return Inertia::render('home', [
            'wallets' => $wallets,
            'income' => [
                'overdue' => self::tally($overdue),
                'this_month' => self::tally($thisMonth),
            ],
            'taxes_this_month' => round((float) $taxes->sum(fn (PlanItem $i): float => $i->open()), 2),
            'not_matched' => Plan::notMatched()->count(),
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
     * @param  Collection<string, PlanItem>  $items
     * @return array{count: int, total: float}
     */
    private static function tally(Collection $items): array
    {
        return [
            'count' => $items->count(),
            'total' => round((float) $items->sum(fn (PlanItem $i): float => $i->open()), 2),
        ];
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\Rates;
use App\Support\WalletBalances;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // A "!" on Bank import while planned items wait in a concluded period.
            'attention' => $request->user() ? fn (): array => [
                'not_matched' => Plan::notMatched()->count(),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The finance lookup lists the transaction edit form needs, shared on every
            // authenticated page so a transactions table can be dropped in ANYWHERE and
            // its edit modal just works — one source of truth, no per-page wiring. A
            // closure, so it evaluates on full loads and refreshes on a partial reload
            // (e.g. after inline-creating an entity) but not on unrelated partials.
            'financeLookups' => $request->user() ? fn (): array => [
                'wallets' => self::wallets(),
                // The open plan items, so a transaction typed by hand can say which
                // one it pays ("Pays planned item").
                'planItems' => array_values(Plan::items()->filter(fn (PlanItem $i): bool => ! $i->isDone())
                    ->map(fn (PlanItem $i): array => [
                        'key' => $i->key,
                        'date' => $i->date()->toDateString(),
                        'entity_id' => $i->template->entity_id,
                        'wallet_id' => $i->template->wallet_id,
                        'type' => $i->template->type,
                        'description' => $i->template->description,
                        'open' => $i->open(),
                    ])->all()),
                'entities' => Entity::query()->orderBy('name')->get(['id', 'name', 'type']),
                'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
                // Each rate with its dated changes, so a form's preview uses the
                // percentage in force on the row's invoice date, like the server.
                'vatRates' => VatRate::query()->orderBy('rate')->get(['id', 'name', 'rate'])
                    ->map(fn (VatRate $r): array => [...$r->only(['id', 'name', 'rate']), 'changes' => Rates::changes('vat', $r->id)]),
                'withheldRates' => WithheldTaxRate::query()->orderBy('rate')->get(['id', 'name', 'rate'])
                    ->map(fn (WithheldTaxRate $r): array => [...$r->only(['id', 'name', 'rate']), 'changes' => Rates::changes('withheld', $r->id)]),
            ] : null,
        ];
    }

    /**
     * The wallets for the forms; one that takes bank fees also carries the fee
     * amounts it is charged most often (last year), as quick picks.
     *
     * @return list<array{id: int, name: string, fee_presets: list<string>|null}>
     */
    private static function wallets(): array
    {
        return array_values(Wallet::query()->orderBy('name')->get()
            ->map(fn (Wallet $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'concluded_until' => $w->concluded_until?->toDateString(),
                'fee_presets' => $w->takesBankFees() ? self::feePresets($w) : null,
            ])->all());
    }

    /** @return list<string> */
    private static function feePresets(Wallet $wallet): array
    {
        return array_values(Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('entity_id', $wallet->fee_entity_id)
            ->where('category_id', $wallet->fee_category_id)
            ->whereDate('date', '>=', now()->subYear()->toDateString())
            ->get(['net', 'vat_amount', 'withheld_amount', 'fmy_amount', 'efka_employee_amount'])
            ->map(fn (Transaction $t): string => number_format(WalletBalances::cashTotal($t), 2, '.', ''))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(4)
            ->map(fn (int|string $amount): string => (string) $amount)
            ->sort(SORT_NUMERIC)
            ->all());
    }
}

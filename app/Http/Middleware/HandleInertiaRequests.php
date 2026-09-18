<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Entity;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The finance lookup lists the transaction edit form needs, shared on every
            // authenticated page so a transactions table can be dropped in ANYWHERE and
            // its edit modal just works — one source of truth, no per-page wiring. A
            // closure, so it evaluates on full loads and refreshes on a partial reload
            // (e.g. after inline-creating an entity) but not on unrelated partials.
            'financeLookups' => $request->user() ? fn (): array => [
                'wallets' => Wallet::query()->orderBy('name')->get(['id', 'name']),
                'entities' => Entity::query()->orderBy('name')->get(['id', 'name', 'type']),
                'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
                'vatRates' => VatRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
                'withheldRates' => WithheldTaxRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
            ] : null,
        ];
    }
}

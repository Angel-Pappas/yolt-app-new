<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Support\RecurrenceGenerator;
use App\Support\TaxSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entities (counterparties) CRUD, split by type. Available to any active user;
 * shared company data with a created-by `user_id` audit field.
 *
 * Entities live under the sidebar's "Entities" section, one list per type; every
 * entity has a type. "State" is a separate concern (the tax pages), so it is not a
 * user-managed list here.
 */
class EntityController extends Controller
{
    /** URL slug → stored `type`. */
    private const SLUGS = [
        'customers' => 'customer',
        'suppliers' => 'supplier',
        'contractors' => 'contractor',
        'employees' => 'employee',
        'shareholders' => 'shareholder',
    ];

    /** A bare `/entities` visit lands on the first list. */
    public function index(): RedirectResponse
    {
        return redirect()->route('entities.type', 'customers');
    }

    /** A single type's list. */
    public function byType(string $slug): Response
    {
        return Inertia::render('entities/list', [
            'entities' => Entity::query()
                ->where('type', self::SLUGS[$slug])
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'vat_number']),
            'title' => ucfirst($slug),
            'singular' => rtrim($slug, 's'),
        ]);
    }

    /**
     * An entity's own page: the edit form, its recurrences and every transaction
     * with this counterparty. Transactions
     * carry their full shape and the finance lookups ride along (shared), so a row
     * opens in the edit dialog.
     */
    public function show(Entity $entity): Response
    {
        $transactions = Transaction::query()
            ->withListData()
            ->where('entity_id', $entity->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        // Where the back link goes: the entity's own list, or the tax pages for the
        // State (it has no entity list of its own).
        $slug = array_search($entity->type, self::SLUGS, true);

        return Inertia::render('entities/show', [
            'entity' => $entity->only(['id', 'name', 'type', 'vat_number']),
            'transactions' => $transactions,
            'recurrences' => $entity->recurrences()->with('entries.lines')->orderByDesc('id')->get(),
            'listHref' => $slug !== false ? "/entities/{$slug}" : '/taxes',
            'listTitle' => $slug !== false ? ucfirst($slug) : 'State',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entity = new Entity($this->validateEntity($request));
        $entity->user_id = $request->user()->id;
        $entity->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity created.')]);

        return back();
    }

    /**
     * A type change can change when its recurrences are paid (contractors and
     * employees on the last working day of the month), so their transactions — and
     * the taxes they feed — are re-synced.
     */
    public function update(Request $request, Entity $entity): RedirectResponse
    {
        DB::transaction(function () use ($request, $entity) {
            $entity->update($this->validateEntity($request));

            if ($entity->wasChanged('type')) {
                $entity->recurrences()->with('entries.lines')->get()
                    ->each(fn (Recurrence $r) => RecurrenceGenerator::sync($r));
                TaxSync::run();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity updated.')]);

        return back();
    }

    public function destroy(Entity $entity): RedirectResponse
    {
        $entity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntity(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Entity::TYPES)],
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        // Normalize a blank/absent VAT number to null (the transaction form's inline
        // create doesn't send one).
        $data['vat_number'] = ($data['vat_number'] ?? null) ?: null;

        return $data;
    }
}

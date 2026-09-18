<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entities (counterparties) CRUD, split by type. Available to any active user;
 * shared company data with a created-by `user_id` audit field.
 *
 * Entities live under the sidebar's "Entities" section, one list per type. A null
 * `type` is the unclassified "Cheese" bucket, where each entity is assigned a type
 * and migrated out. "State" is a separate concern (the tax pages), so it is not a
 * user-managed list here.
 */
class EntityController extends Controller
{
    /** URL slug → stored `type` (null = the Cheese bucket). */
    private const SLUGS = [
        'customers' => 'customer',
        'suppliers' => 'supplier',
        'contractors' => 'contractor',
        'employees' => 'employee',
        'cheese' => null,
    ];

    /** A bare `/entities` visit lands on the first list. */
    public function index(): RedirectResponse
    {
        return redirect()->route('entities.type', 'customers');
    }

    /** A single type's list (or the Cheese bucket of unclassified entities). */
    public function byType(string $slug): Response
    {
        $type = self::SLUGS[$slug];
        $classify = $slug === 'cheese';

        return Inertia::render('entities/list', [
            'entities' => Entity::query()
                ->when($type !== null, fn ($q) => $q->where('type', $type))
                ->when($type === null, fn ($q) => $q->whereNull('type'))
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'vat_number']),
            'slug' => $slug,
            'title' => $classify ? 'Cheese' : ucfirst($slug),
            'singular' => $classify ? 'entity' : rtrim($slug, 's'),
            'classify' => $classify,
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

    public function update(Request $request, Entity $entity): RedirectResponse
    {
        $entity->update($this->validateEntity($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity updated.')]);

        return back();
    }

    /** Assign a type to many entities at once — the Cheese bucket's bulk classify. */
    public function bulkType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'type' => ['required', Rule::in(Entity::TYPES)],
        ]);

        Entity::whereIn('id', $data['ids'])->update(['type' => $data['type']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entities moved.')]);

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
            'type' => ['nullable', Rule::in(Entity::TYPES)],
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        // Normalize a blank/absent VAT number or type to null (neither is sent when an
        // entity is created inline from the transaction form).
        $data['vat_number'] = ($data['vat_number'] ?? null) ?: null;
        $data['type'] = ($data['type'] ?? null) ?: null;

        return $data;
    }
}

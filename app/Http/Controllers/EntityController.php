<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entities (counterparties) CRUD. Gated by `can:access-finance`; shared company
 * data with a created-by `user_id` audit field.
 */
class EntityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('entities/index', [
            'entities' => Entity::query()
                ->orderBy('name')
                ->get(['id', 'name', 'vat_number']),
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
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        $data['vat_number'] = $data['vat_number'] ?: null;

        return $data;
    }
}

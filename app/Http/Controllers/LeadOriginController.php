<?php

namespace App\Http\Controllers;

use App\Models\LeadOrigin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lead origins CRUD. Gated by `can:access-crm`; shared company data with a
 * created-by `user_id` audit field. A new origin is appended to the end
 * (position = max + 1).
 */
class LeadOriginController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('lead-origins/index', [
            'leadOrigins' => LeadOrigin::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'name', 'position']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $origin = new LeadOrigin($this->validateOrigin($request));
        $origin->user_id = $request->user()->id;
        $origin->position = (int) LeadOrigin::query()->max('position') + 1;
        $origin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead origin created.')]);

        return back();
    }

    public function update(Request $request, LeadOrigin $leadOrigin): RedirectResponse
    {
        $leadOrigin->update($this->validateOrigin($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead origin updated.')]);

        return back();
    }

    public function destroy(LeadOrigin $leadOrigin): RedirectResponse
    {
        $leadOrigin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead origin deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrigin(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}

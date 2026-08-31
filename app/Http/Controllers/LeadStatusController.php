<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lead statuses CRUD. Gated by `can:access-crm`; shared company data with a
 * created-by `user_id` audit field. A new status is appended to the end of the
 * pipeline (position = max + 1).
 */
class LeadStatusController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('lead-statuses/index', [
            'leadStatuses' => LeadStatus::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'name', 'position']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $status = new LeadStatus($this->validateStatus($request));
        $status->user_id = $request->user()->id;
        $status->position = (int) LeadStatus::query()->max('position') + 1;
        $status->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead status created.')]);

        return back();
    }

    public function update(Request $request, LeadStatus $leadStatus): RedirectResponse
    {
        $leadStatus->update($this->validateStatus($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead status updated.')]);

        return back();
    }

    public function destroy(LeadStatus $leadStatus): RedirectResponse
    {
        $leadStatus->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead status deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStatus(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}

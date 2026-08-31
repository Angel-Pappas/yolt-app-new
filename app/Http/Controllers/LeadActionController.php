<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Lead actions — the History log on a lead, nested under the lead. Gated by
 * `can:access-crm`. The author is the acting user (denormalized `author_name`,
 * since a colleague's name can't be joined under the CRM's shared-read model);
 * `action_date` is editable and defaults to today.
 */
class LeadActionController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        $action = new LeadAction($this->validateAction($request));
        $action->user_id = $request->user()->id;
        $action->author_name = $request->user()->name;
        $lead->actions()->save($action);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action logged.')]);

        return back();
    }

    public function update(Request $request, Lead $lead, LeadAction $action): RedirectResponse
    {
        abort_unless($action->lead_id === $lead->id, 404);

        $action->update($this->validateAction($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action updated.')]);

        return back();
    }

    public function destroy(Lead $lead, LeadAction $action): RedirectResponse
    {
        abort_unless($action->lead_id === $lead->id, 404);

        $action->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAction(Request $request): array
    {
        return $request->validate([
            'action_date' => ['required', 'date'],
            'body' => ['required', 'string'],
        ]);
    }
}

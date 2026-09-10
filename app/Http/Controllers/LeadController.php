<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadOrigin;
use App\Models\LeadStatus;
use App\Models\Project;
use App\Support\Crm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Leads — the CRM chasing pipeline. Gated by `can:access-crm`; shared company
 * data with a created-by `user_id` audit field. A new lead's "No." (`sort_order`)
 * is assigned at max+1 on save and never reused. Origin and status reference the
 * lookup lists.
 */
class LeadController extends Controller
{
    public function index(): Response
    {
        $query = Lead::query()->with(['origin:id,name', 'status:id,name']);

        // Hide converted leads (they've graduated to Projects). Searching and
        // per-column filtering happen client-side in the shared list view.
        // The is-null half is required — a bare != also drops null-status rows.
        $conversionId = LeadStatus::query()->where('is_conversion', true)->value('id');
        if ($conversionId !== null) {
            $query->where(function ($q) use ($conversionId) {
                $q->whereNull('status_id')->orWhere('status_id', '!=', $conversionId);
            });
        }

        return Inertia::render('leads/index', [
            'leads' => $query->orderBy('sort_order')->orderByDesc('id')->get([
                'id', 'sort_order', 'name', 'origin_id', 'status_id', 'website',
                'contact_name', 'contact_position', 'contact_email',
                'contact_phone', 'contact_landline', 'description', 'next_step',
                'campaign_platform', 'campaign_we_are', 'campaign_we_want',
            ]),
            'statuses' => LeadStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name', 'is_conversion']),
            'origins' => LeadOrigin::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        $lead->load(['origin:id,name', 'status:id,name']);

        return Inertia::render('leads/show', [
            'lead' => $lead,
            'actions' => $lead->actions()
                ->orderByDesc('action_date')
                ->orderByDesc('id')
                ->get(['id', 'action_date', 'body', 'author_name', 'user_id']),
            'users' => Crm::usersForPicker($request->user()),
            'contacts' => $lead->contacts()
                ->orderBy('name')
                ->get(['id', 'name', 'position', 'phone', 'landline', 'website', 'email']),
            'project' => Project::query()->where('lead_id', $lead->id)->first(['id', 'name']),
            'statuses' => LeadStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name', 'is_conversion']),
            'origins' => LeadOrigin::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lead = new Lead($this->validateLead($request));
        $lead->user_id = $request->user()->id;
        // withTrashed so a soft-deleted lead keeps its number — never reused.
        $lead->sort_order = (int) Lead::withTrashed()->max('sort_order') + 1;
        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead added.')]);

        return back();
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($this->validateLead($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead updated.')]);

        return back();
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead deleted.')]);

        return back();
    }

    /** Inline edit of a single lead field from the list (next step / status). */
    public function updateNextStep(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validate(['next_step' => ['nullable', 'string']]));

        return back();
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validate([
            'status_id' => ['nullable', 'integer', 'exists:lead_statuses,id'],
        ]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLead(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'origin_id' => ['nullable', 'integer', 'exists:lead_origins,id'],
            'status_id' => ['nullable', 'integer', 'exists:lead_statuses,id'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_position' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_landline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'next_step' => ['nullable', 'string'],
            'campaign_platform' => ['nullable', 'in:facebook,instagram'],
            'campaign_we_are' => ['nullable', 'string'],
            'campaign_we_want' => ['nullable', 'string'],
        ]);
    }
}

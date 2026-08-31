<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadOrigin;
use App\Models\LeadStatus;
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
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->input('q')) ?: null,
            'status' => $request->filled('status') ? (int) $request->input('status') : null,
            'origin' => $request->filled('origin') ? (int) $request->input('origin') : null,
        ];

        $query = Lead::query()->with(['origin:id,name', 'status:id,name']);

        if ($filters['q'] !== null) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('contact_phone', 'like', $term)
                    ->orWhere('next_step', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }
        if ($filters['status'] !== null) {
            $query->where('status_id', $filters['status']);
        }
        if ($filters['origin'] !== null) {
            $query->where('origin_id', $filters['origin']);
        }

        return Inertia::render('leads/index', [
            'leads' => $query->orderBy('sort_order')->orderByDesc('id')->get([
                'id', 'sort_order', 'name', 'origin_id', 'status_id', 'website',
                'contact_name', 'contact_position', 'contact_email',
                'contact_phone', 'contact_landline', 'description', 'next_step',
            ]),
            'filters' => $filters,
            'statuses' => LeadStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
            'origins' => LeadOrigin::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function show(Lead $lead): Response
    {
        $lead->load(['origin:id,name', 'status:id,name']);

        return Inertia::render('leads/show', [
            'lead' => $lead,
            'actions' => $lead->actions()
                ->orderByDesc('action_date')
                ->orderByDesc('id')
                ->get(['id', 'action_date', 'body', 'author_name']),
            'statuses' => LeadStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
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
        ]);
    }
}

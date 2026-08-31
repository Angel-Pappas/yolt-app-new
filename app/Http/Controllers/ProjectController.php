<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Project;
use App\Models\ProjectStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Projects — a won lead graduated into its own tracked entity. Gated by
 * `can:access-crm`; shared company data with a created-by `user_id` audit field. A
 * new project's "No." (`sort_order`) is assigned at `withTrashed()->max+1` and
 * never reused. Client/contact details stay on the linked lead; the list surfaces
 * the lead's main contact name as "Client".
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->input('q')) ?: null,
            'status' => $request->filled('status') ? (int) $request->input('status') : null,
        ];

        $query = Project::query()->with([
            'status:id,name',
            'lead:id,contact_name',
        ]);

        if ($filters['q'] !== null) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('next_step', 'like', $term);
            });
        }
        if ($filters['status'] !== null) {
            $query->where('status_id', $filters['status']);
        }

        return Inertia::render('projects/index', [
            'projects' => $query->orderBy('sort_order')->orderByDesc('id')->get([
                'id', 'sort_order', 'name', 'lead_id', 'status_id',
                'description', 'value', 'estimated_months', 'next_step',
            ]),
            'filters' => $filters,
            'statuses' => ProjectStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load(['status:id,name', 'lead:id,contact_name']);

        return Inertia::render('projects/show', [
            'project' => $project,
            'actions' => $project->actions()
                ->orderByDesc('action_date')
                ->orderByDesc('id')
                ->get(['id', 'action_date', 'body', 'author_name']),
            'statuses' => ProjectStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $project = new Project($this->validateProject($request));
        $project->user_id = $request->user()->id;
        // withTrashed so a soft-deleted project keeps its number — never reused.
        $project->sort_order = (int) Project::withTrashed()->max('sort_order') + 1;
        $project->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project added.')]);

        return back();
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validateProject($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return back();
    }

    /**
     * Graduate a lead into a project: create the project linked to the lead and
     * flip the lead to the flagged "Converted" status (so it drops out of the
     * chasing pipeline). A lead only converts once — if a project already exists
     * for it, redirect to that one instead of creating a duplicate.
     */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $existing = Project::query()->where('lead_id', $lead->id)->first();
        if ($existing !== null) {
            return to_route('projects.show', $existing);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = DB::transaction(function () use ($data, $lead, $request) {
            $project = new Project(['name' => $data['name'], 'lead_id' => $lead->id]);
            $project->user_id = $request->user()->id;
            $project->sort_order = (int) Project::withTrashed()->max('sort_order') + 1;
            $project->save();

            $conversionId = LeadStatus::query()->where('is_conversion', true)->value('id');
            if ($conversionId !== null) {
                $lead->update(['status_id' => $conversionId]);
            }

            return $project;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead converted to a project.')]);

        return to_route('projects.show', $project);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer', 'exists:project_statuses,id'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'estimated_months' => ['nullable', 'integer', 'min:0'],
            'next_step' => ['nullable', 'string'],
        ]);
    }
}

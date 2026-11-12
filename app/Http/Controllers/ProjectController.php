<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Transaction;
use App\Support\ProjectOverview;
use App\Support\TaxSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Projects: the agreed work for each customer. A project is a container —
 * its installments are ordinary income transactions (added from the project's page
 * with the shared transaction form), so VAT, the projection and every tax follow
 * from them like from any other transaction.
 */
class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()
            ->with(['entity:id,name', 'transactions' => fn ($q) => $q->select('id', 'project_id', 'date', 'net', 'income_status')])
            ->orderBy('name')
            ->get();

        return Inertia::render('projects/index', [
            'projects' => $projects->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'entity' => ['id' => $p->entity_id, 'name' => $p->entity->name ?? '—'],
                'agreed_total' => $p->agreed_total,
                'is_excluded' => $p->is_excluded,
                ...ProjectOverview::summarize($p, $p->transactions),
            ])->values(),
        ]);
    }

    public function show(Project $project): Response
    {
        $transactions = Transaction::query()
            ->withListData()
            ->where('project_id', $project->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return Inertia::render('projects/show', [
            'project' => [
                ...$project->only(['id', 'name', 'entity_id', 'agreed_total', 'notes', 'is_excluded']),
                'entity' => $project->entity()->first(['id', 'name']),
            ],
            'summary' => ProjectOverview::summarize($project, $transactions),
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $project = new Project($this->validateProject($request));
        $project->user_id = $request->user()->id;
        $project->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validateProject($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    /**
     * Switch a whole project off or on: every installment that hasn't been
     * reconciled follows; new installments start the same way.
     */
    public function exclude(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(['excluded' => ['required', 'boolean']]);
        $excluded = (bool) $data['excluded'];

        DB::transaction(function () use ($project, $excluded) {
            $project->update(['is_excluded' => $excluded]);
            $project->transactions()->where('is_reconciled', false)->update(['is_excluded' => $excluded]);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $excluded ? __('Project excluded.') : __('Project included.')]);

        return back();
    }

    /**
     * Deleting a project keeps its installments (they are real transactions) and
     * just detaches them.
     */
    public function destroy(Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project) {
            $project->transactions()->update(['project_id' => null]);
            $project->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'agreed_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['agreed_total'] = isset($data['agreed_total']) ? round((float) $data['agreed_total'], 2) : null;
        $data['notes'] = ($data['notes'] ?? null) ?: null;

        return $data;
    }
}

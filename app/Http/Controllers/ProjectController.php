<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Projects: the work done for customers. A project has a name, a customer
 * (a placeholder customer when the real one isn't known yet) and its deliverables,
 * whose net amounts add up to its value. The money is planned on the customer, as
 * payment lines that may cover several projects at once.
 */
class ProjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('projects/index', [
            'projects' => Project::query()->with(['entity:id,name', 'deliverables'])->orderBy('name')->get()
                ->map(fn (Project $p): array => self::row($p))->values(),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load(['entity:id,name', 'deliverables']);

        return Inertia::render('projects/show', ['project' => self::row($project)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProject($request);
        $project = DB::transaction(function () use ($request, $data): Project {
            $project = new Project(['name' => $data['name'], 'entity_id' => $data['entity_id'], 'notes' => $data['notes']]);
            $project->user_id = $request->user()->id;
            $project->save();
            self::writeDeliverables($project, $data['deliverables']);

            return $project;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validateProject($request);
        DB::transaction(function () use ($project, $data): void {
            $project->update(['name' => $data['name'], 'entity_id' => $data['entity_id'], 'notes' => $data['notes']]);
            self::writeDeliverables($project, $data['deliverables']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }

    /**
     * A project as the lists and its page show it.
     *
     * @return array<string, mixed>
     */
    public static function row(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'notes' => $p->notes,
            'entity' => ['id' => $p->entity_id, 'name' => $p->entity->name ?? '—'],
            'entity_id' => $p->entity_id,
            'value' => $p->totalValue(),
            'deliverables' => $p->deliverables->map(fn (ProjectDeliverable $d): array => [
                'id' => $d->id, 'name' => $d->name, 'net' => (float) $d->net,
            ])->values(),
        ];
    }

    /**
     * Replace a project's deliverables with the submitted ones (in their order).
     *
     * @param  list<array{name: string, net: float|string}>  $deliverables
     */
    private static function writeDeliverables(Project $project, array $deliverables): void
    {
        $project->deliverables()->delete();
        foreach ($deliverables as $i => $d) {
            $project->deliverables()->create(['name' => $d['name'], 'net' => round((float) $d['net'], 2), 'position' => $i]);
        }
    }

    /**
     * @return array{name: string, entity_id: int, notes: string|null, deliverables: list<array{name: string, net: float|string}>}
     */
    private function validateProject(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer', Rule::exists(Entity::class, 'id')->where('type', 'customer')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['required', 'array', 'min:1'],
            'deliverables.*.name' => ['required', 'string', 'max:255'],
            'deliverables.*.net' => ['required', 'numeric', 'min:0'],
        ], [
            'entity_id.exists' => __('Pick a customer.'),
            'deliverables.required' => __('Add at least one deliverable.'),
        ]);

        return [
            'name' => $data['name'],
            'entity_id' => (int) $data['entity_id'],
            'notes' => ($data['notes'] ?? null) ?: null,
            'deliverables' => array_values($data['deliverables']),
        ];
    }
}

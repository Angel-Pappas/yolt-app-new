<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Project actions — the History log on a project, nested under the project. Gated
 * by `can:access-crm`. The exact parallel of LeadActionController.
 */
class ProjectActionController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $action = new ProjectAction($this->validateAction($request));
        $action->user_id = $request->user()->id;
        $action->author_name = $request->user()->name;
        $project->actions()->save($action);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action logged.')]);

        return back();
    }

    public function update(Request $request, Project $project, ProjectAction $action): RedirectResponse
    {
        abort_unless($action->project_id === $project->id, 404);

        $action->update($this->validateAction($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action updated.')]);

        return back();
    }

    public function destroy(Project $project, ProjectAction $action): RedirectResponse
    {
        abort_unless($action->project_id === $project->id, 404);

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

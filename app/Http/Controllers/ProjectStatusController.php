<?php

namespace App\Http\Controllers;

use App\Models\ProjectStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Project statuses CRUD. Gated by `can:access-crm`; shared company data with a
 * created-by `user_id` audit field. A new status appends at max+1.
 */
class ProjectStatusController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('project-statuses/index', [
            'projectStatuses' => ProjectStatus::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'name', 'position']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $status = new ProjectStatus($this->validateStatus($request));
        $status->user_id = $request->user()->id;
        $status->position = (int) ProjectStatus::query()->max('position') + 1;
        $status->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project status created.')]);

        return back();
    }

    public function update(Request $request, ProjectStatus $projectStatus): RedirectResponse
    {
        $projectStatus->update($this->validateStatus($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project status updated.')]);

        return back();
    }

    public function destroy(ProjectStatus $projectStatus): RedirectResponse
    {
        $projectStatus->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project status deleted.')]);

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

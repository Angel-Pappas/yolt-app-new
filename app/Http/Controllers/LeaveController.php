<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\LeaveEntry;
use App\Support\Leave;
use App\Support\Termination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * An employee's leave entries (on their page) and the termination-cost estimate.
 */
class LeaveController extends Controller
{
    public function store(Request $request, Entity $entity): RedirectResponse
    {
        abort_unless($entity->type === 'employee', 404);

        $leave = new LeaveEntry($this->validated($request, $entity));
        $leave->entity_id = $entity->id;
        $leave->user_id = $request->user()->id;
        $leave->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave added.')]);

        return back();
    }

    public function update(Request $request, Entity $entity, LeaveEntry $leave): RedirectResponse
    {
        abort_unless($leave->entity_id === $entity->id, 404);
        $leave->update($this->validated($request, $entity));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave updated.')]);

        return back();
    }

    public function destroy(Entity $entity, LeaveEntry $leave): RedirectResponse
    {
        abort_unless($leave->entity_id === $entity->id, 404);
        $leave->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave deleted.')]);

        return back();
    }

    /** What dismissing the employee on a date would cost (see Termination). */
    public function termination(Request $request, Entity $entity): JsonResponse
    {
        abort_unless($entity->type === 'employee', 404);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'notice' => ['nullable', 'boolean'],
        ]);

        return response()->json(Termination::estimate($entity, (string) $data['date'], $request->boolean('notice')));
    }

    /**
     * The days default to the working days between the dates (no weekends or public
     * holidays, by the employee's working week) when left blank.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Entity $entity): array
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:366'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $data['days'] = isset($data['days'])
            ? round((float) $data['days'], 1)
            : Leave::workingDays(
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date']),
                $entity->employeeProfile !== null ? $entity->employeeProfile->work_days_per_week : 5,
            );
        $data['note'] = ($data['note'] ?? null) ?: null;

        return $data;
    }
}

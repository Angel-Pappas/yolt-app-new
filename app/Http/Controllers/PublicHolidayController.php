<?php

namespace App\Http\Controllers;

use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public holidays CRUD — a Configuration setup list. Available to any active user;
 * shared company data with a created-by `user_id`. The dates feed
 * `App\Support\WorkingDays` when computing tax payment (last working day) dates.
 */
class PublicHolidayController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('public-holidays/index', [
            'holidays' => PublicHoliday::query()
                ->orderBy('date')
                ->get(['id', 'date', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $holiday = new PublicHoliday($this->validateHoliday($request));
        $holiday->user_id = $request->user()->id;
        $holiday->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public holiday created.')]);

        return back();
    }

    public function update(Request $request, PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->update($this->validateHoliday($request, $publicHoliday));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public holiday updated.')]);

        return back();
    }

    public function destroy(PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public holiday deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHoliday(Request $request, ?PublicHoliday $ignore = null): array
    {
        return $request->validate([
            'date' => [
                'required',
                'date',
                // Unique among active (non-deleted) rows so the same day can't be
                // listed twice; a soft-deleted date can be re-added.
                Rule::unique('public_holidays', 'date')
                    ->whereNull('deleted_at')
                    ->ignore($ignore?->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

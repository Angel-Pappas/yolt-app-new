<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Agreements: the signed file and dates of each agreement with an entity, for the
 * record and to see a renewal coming. Files are private (stored outside the public
 * folder) and served only to signed-in users through {@see download()}.
 */
class AgreementController extends Controller
{
    /** The private disk folder the files live in. */
    private const FOLDER = 'agreements';

    public function index(): Response
    {
        return Inertia::render('agreements/index', [
            'agreements' => self::rows(Agreement::query()),
            'warning_days' => Setting::current()->agreement_warning_days,
        ]);
    }

    /**
     * Agreements as the shared table shows them — also used by an entity's page.
     *
     * @param  Builder<Agreement>  $query
     * @return list<array<string, mixed>>
     */
    public static function rows(Builder $query): array
    {
        $today = Carbon::today();
        $warning = (int) Setting::current()->agreement_warning_days;

        return array_values($query->with('entity:id,name,type')->orderBy('end_date')->orderBy('start_date')->get()
            ->map(fn (Agreement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'entity_id' => $a->entity_id,
                'entity' => ['id' => $a->entity_id, 'name' => $a->entity->name ?? '—'],
                'start_date' => $a->start_date->format('Y-m-d'),
                'end_date' => $a->end_date?->format('Y-m-d'),
                'notes' => $a->notes,
                'file_name' => $a->file_name,
                'state' => $a->state($today, $warning),
                'days_left' => $a->end_date !== null ? (int) $today->diffInDays($a->end_date, false) : null,
            ])->all());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $agreement = new Agreement($data);
        $agreement->user_id = $request->user()->id;
        $this->attach($agreement, $request->file('file'));
        $agreement->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agreement added.')]);

        return back();
    }

    /** A new file replaces the stored one; without one the stored file is kept. */
    public function update(Request $request, Agreement $agreement): RedirectResponse
    {
        $agreement->fill($this->validated($request));
        if ($request->hasFile('file')) {
            $old = $agreement->file_path;
            $this->attach($agreement, $request->file('file'));
            if ($old !== null) {
                Storage::disk('local')->delete($old);
            }
        }
        $agreement->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agreement updated.')]);

        return back();
    }

    /** Soft-deleted: the file is kept with it (nothing is lost by a slip). */
    public function destroy(Agreement $agreement): RedirectResponse
    {
        $agreement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agreement deleted.')]);

        return back();
    }

    /** The file, shown in the browser where it can be (PDF, image). */
    public function download(Agreement $agreement): StreamedResponse
    {
        abort_if($agreement->file_path === null || ! Storage::disk('local')->exists($agreement->file_path), 404);

        return Storage::disk('local')->response($agreement->file_path, $agreement->file_name);
    }

    /** How many days ahead an ending agreement is flagged (Agreements page). */
    public function updateWarning(Request $request): RedirectResponse
    {
        $data = $request->validate(['warning_days' => ['required', 'integer', 'between:1,365']]);
        Setting::current()->update(['agreement_warning_days' => (int) $data['warning_days']]);

        return back();
    }

    private function attach(Agreement $agreement, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }
        $path = $file->storeAs(
            self::FOLDER,
            Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'),
            'local',
        );
        // A file that could not be written must not look saved.
        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be stored. Please try again.')]);
        }
        $agreement->file_path = $path;
        $agreement->file_name = $file->getClientOriginalName();
        $agreement->file_size = $file->getSize();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // 20 MB; documents and scans.
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt,xls,xlsx,jpg,jpeg,png,txt'],
        ]);
        unset($data['file']);
        $data['end_date'] = ($data['end_date'] ?? null) ?: null;
        $data['notes'] = ($data['notes'] ?? null) ?: null;

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DeMinimisAid;
use App\Support\DeMinimisExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Finance ▸ De minimis — the company's record of every de minimis grant, so the
 * table grant applications ask for never has to be rebuilt from paper. Available to
 * any active user; shared company data with a created-by `user_id`. The whole list
 * downloads as Markdown or Excel (`App\Support\DeMinimisExport`).
 */
class DeMinimisAidController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('de-minimis/index', [
            'aids' => DeMinimisExport::rows()->map(fn (DeMinimisAid $aid) => [
                'id' => $aid->id,
                'programme' => $aid->programme,
                'decision_number' => $aid->decision_number,
                'decision_date' => $aid->decision_date->format('Y-m-d'),
                'approved_amount' => $aid->approved_amount,
                'paid_amount' => $aid->paid_amount,
                'last_payment_date' => $aid->last_payment_date?->format('Y-m-d'),
                'beneficiary_name' => $aid->beneficiary_name,
                'beneficiary_vat_number' => $aid->beneficiary_vat_number,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $aid = new DeMinimisAid($this->validateAid($request));
        $aid->user_id = $request->user()->id;
        $aid->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grant added.')]);

        return back();
    }

    public function update(Request $request, DeMinimisAid $deMinimisAid): RedirectResponse
    {
        $deMinimisAid->update($this->validateAid($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grant updated.')]);

        return back();
    }

    public function destroy(DeMinimisAid $deMinimisAid): RedirectResponse
    {
        $deMinimisAid->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grant deleted.')]);

        return back();
    }

    public function markdown(): HttpResponse
    {
        return response(DeMinimisExport::markdown(DeMinimisExport::rows()), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename('md').'"',
        ]);
    }

    public function excel(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'deminimis');
        DeMinimisExport::xlsx(DeMinimisExport::rows(), $path);

        return response()->download($path, $this->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function filename(string $extension): string
    {
        return 'de-minimis-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAid(Request $request): array
    {
        return $request->validate([
            'programme' => ['required', 'string', 'max:2000'],
            'decision_number' => ['required', 'string', 'max:255'],
            'decision_date' => ['required', 'date'],
            'approved_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'last_payment_date' => ['nullable', 'date'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'beneficiary_vat_number' => ['required', 'string', 'max:20'],
        ]);
    }
}

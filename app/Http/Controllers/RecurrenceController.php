<?php

namespace App\Http\Controllers;

use App\Models\Recurrence;
use App\Support\RecurrenceGenerator;
use App\Support\TaxSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Recurring transactions defined on an entity. A recurrence carries a fixed template
 * plus a cadence/window and a dated amount timeline (its entries, managed wholesale
 * here like a transaction's VAT lines). Every write re-runs the generator so the
 * real transactions immediately match; reconciled rows stay frozen.
 */
class RecurrenceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRecurrence($request);

        DB::transaction(function () use ($request, $data) {
            $recurrence = new Recurrence($this->recurrenceAttributes($data));
            $recurrence->user_id = $request->user()->id;
            $recurrence->save();
            $this->writeEntries($recurrence, $data['entries']);
            RecurrenceGenerator::sync($recurrence);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring transaction created.')]);

        return back();
    }

    public function update(Request $request, Recurrence $recurrence): RedirectResponse
    {
        $data = $this->validateRecurrence($request);

        DB::transaction(function () use ($recurrence, $data) {
            $recurrence->update($this->recurrenceAttributes($data));
            $recurrence->entries()->delete();
            $this->writeEntries($recurrence, $data['entries']);
            RecurrenceGenerator::sync($recurrence->fresh());
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring transaction updated.')]);

        return back();
    }

    public function destroy(Recurrence $recurrence): RedirectResponse
    {
        DB::transaction(function () use ($recurrence) {
            $recurrence->delete();
            // Trashed → the generator drops its unreconciled rows, keeps reconciled ones.
            RecurrenceGenerator::sync($recurrence);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring transaction deleted.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recurrenceAttributes(array $data): array
    {
        return [
            'entity_id' => $data['entity_id'],
            'type' => $data['type'],
            'description' => $data['description'] ?? '',
            'category_id' => $data['category_id'] ?? null,
            'wallet_id' => $data['wallet_id'],
            'vat_rate_id' => $data['is_payroll'] ? null : ($data['vat_rate_id'] ?? null),
            'withheld_rate_id' => $data['is_payroll'] ? null : ($data['withheld_rate_id'] ?? null),
            'is_payroll' => $data['is_payroll'],
            'interval_count' => $data['interval_count'],
            'interval_unit' => $data['interval_unit'],
            'day_of_month' => $data['interval_unit'] === 'week' ? null : ($data['day_of_month'] ?? null),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'active' => $data['active'] ?? true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function writeEntries(Recurrence $recurrence, array $entries): void
    {
        $payroll = $recurrence->is_payroll;

        $recurrence->entries()->createMany(
            collect(array_values($entries))->map(fn (array $e, int $i) => [
                'start_date' => $e['start_date'],
                'end_date' => $e['end_date'] ?? null,
                'net' => round((float) $e['net'], 2),
                'fmy_amount' => $payroll && isset($e['fmy_amount']) ? round((float) $e['fmy_amount'], 2) : null,
                'efka_employee_amount' => $payroll && isset($e['efka_employee_amount']) ? round((float) $e['efka_employee_amount'], 2) : null,
                'efka_employer_amount' => $payroll && isset($e['efka_employer_amount']) ? round((float) $e['efka_employer_amount'], 2) : null,
                'position' => $i,
            ])->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRecurrence(Request $request): array
    {
        return $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
            'withheld_rate_id' => ['nullable', 'integer', 'exists:withheld_tax_rates,id'],
            'is_payroll' => ['boolean'],
            'interval_count' => ['required', 'integer', 'min:1', 'max:12'],
            'interval_unit' => ['required', Rule::in(Recurrence::UNITS)],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['boolean'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.start_date' => ['required', 'date'],
            'entries.*.end_date' => ['nullable', 'date'],
            'entries.*.net' => ['required', 'numeric', 'min:0'],
            'entries.*.fmy_amount' => ['nullable', 'numeric', 'min:0'],
            'entries.*.efka_employee_amount' => ['nullable', 'numeric', 'min:0'],
            'entries.*.efka_employer_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}

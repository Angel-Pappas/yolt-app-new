<?php

namespace App\Http\Controllers;

use App\Models\Recurrence;
use App\Support\AmountLines;
use App\Support\RecurrenceGenerator;
use App\Support\TaxSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Recurring transactions defined on an entity. A recurrence carries a fixed template
 * (type, category, wallet, description) plus a cadence, and a list of dated periods
 * — each with the same amount shape as a hand-entered transaction (amount lines with
 * their own VAT and optional withholding, under a Net/Total mode; or the payroll
 * Net/FMY/EFKA set when the category is "Payroll"). Periods are managed wholesale
 * here like a transaction's VAT lines. The recurrence's own window is derived from
 * its periods. Every write re-runs the generator so the real transactions
 * immediately match; reconciled rows stay frozen.
 */
class RecurrenceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['entity_id' => ['required', 'integer', 'exists:entities,id']]);
        $data = $this->validateRecurrence($request);

        DB::transaction(function () use ($request, $data) {
            $recurrence = new Recurrence($this->recurrenceAttributes($data));
            $recurrence->entity_id = (int) $request->input('entity_id');
            $recurrence->user_id = $request->user()->id;
            $recurrence->save();
            $this->writeEntries($recurrence, $data['entries']);
            RecurrenceGenerator::sync($recurrence);
            TaxSync::run();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring transaction created.')]);

        return back();
    }

    /**
     * The entity is fixed once created — a recurrence lives on its entity's page — so
     * an `entity_id` in the request is ignored here.
     */
    public function update(Request $request, Recurrence $recurrence): RedirectResponse
    {
        $data = $this->validateRecurrence($request);

        DB::transaction(function () use ($recurrence, $data) {
            $recurrence->update($this->recurrenceAttributes($data));
            $recurrence->entries()->delete(); // cascades to their lines
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
     * The recurrence's own columns. Its window runs from the earliest period's start
     * to the latest period's end (open-ended when that last period has no end), and
     * payroll follows the category — the same rule as a hand-entered transaction.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recurrenceAttributes(array $data): array
    {
        $entries = self::sortedEntries($data['entries']);
        $last = $entries[count($entries) - 1];

        return [
            'type' => $data['type'],
            'description' => $data['description'] ?? '',
            'category_id' => $data['category_id'] ?? null,
            'wallet_id' => $data['wallet_id'],
            'is_payroll' => $data['is_payroll'],
            'interval_count' => $data['interval_count'],
            'interval_unit' => $data['interval_unit'],
            'day_of_month' => $data['interval_unit'] === 'week' ? null : ($data['day_of_month'] ?? null),
            'start_date' => $entries[0]['start_date'],
            'end_date' => $last['end_date'] ?? null,
            'active' => $data['active'] ?? true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function sortedEntries(array $entries): array
    {
        $entries = array_values($entries);
        usort($entries, fn (array $a, array $b) => strcmp((string) $a['start_date'], (string) $b['start_date']));

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function writeEntries(Recurrence $recurrence, array $entries): void
    {
        $payroll = $recurrence->is_payroll;
        $amount = fn (array $e, string $key): ?float => isset($e[$key]) ? round((float) $e[$key], 2) : null;

        foreach (self::sortedEntries($entries) as $i => $e) {
            $entry = $recurrence->entries()->create([
                'start_date' => $e['start_date'],
                'end_date' => $e['end_date'] ?? null,
                'net' => $payroll ? round((float) $e['net'], 2) : 0,
                'fmy_amount' => $payroll ? $amount($e, 'fmy_amount') : null,
                'efka_employee_amount' => $payroll ? $amount($e, 'efka_employee_amount') : null,
                'efka_employer_amount' => $payroll ? $amount($e, 'efka_employer_amount') : null,
                'amount_mode' => $payroll ? 'net' : $e['amount_mode'],
                'position' => $i,
            ]);

            if ($payroll) {
                continue;
            }

            $entry->lines()->createMany(
                collect(array_values($e['lines']))->map(fn (array $l, int $p) => [
                    'amount' => round((float) $l['amount'], 2),
                    'vat_rate_id' => ($l['vat_rate_id'] ?? null) ?: null,
                    'withheld_rate_id' => ($l['withheld_rate_id'] ?? null) ?: null,
                    'position' => $p,
                ])->all()
            );
        }
    }

    /**
     * Mirrors a transaction's validation: payroll (the "Payroll" category) takes a net
     * plus manual FMY/EFKA per period; anything else takes a period's Net/Total mode
     * and its amount lines, each with an optional VAT and withholding rate.
     *
     * @return array<string, mixed>
     */
    private function validateRecurrence(Request $request): array
    {
        $payroll = AmountLines::isPayrollCategory(
            $request->filled('category_id') ? (int) $request->input('category_id') : null
        );

        $rules = [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'interval_count' => ['required', 'integer', 'min:1', 'max:12'],
            'interval_unit' => ['required', Rule::in(Recurrence::UNITS)],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'active' => ['boolean'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.start_date' => ['required', 'date'],
            'entries.*.end_date' => ['nullable', 'date', 'after_or_equal:entries.*.start_date'],
        ];

        if ($payroll) {
            $rules['entries.*.net'] = ['required', 'numeric', 'min:0'];
            $rules['entries.*.fmy_amount'] = ['nullable', 'numeric', 'min:0'];
            $rules['entries.*.efka_employee_amount'] = ['nullable', 'numeric', 'min:0'];
            $rules['entries.*.efka_employer_amount'] = ['nullable', 'numeric', 'min:0'];
        } else {
            $rules['entries.*.amount_mode'] = ['required', 'in:net,total'];
            $rules['entries.*.lines'] = ['required', 'array', 'min:1'];
            $rules['entries.*.lines.*.amount'] = ['required', 'numeric', 'min:0'];
            $rules['entries.*.lines.*.vat_rate_id'] = ['nullable', 'integer', 'exists:vat_rates,id'];
            $rules['entries.*.lines.*.withheld_rate_id'] = ['nullable', 'integer', 'exists:withheld_tax_rates,id'];
        }

        return $request->validate($rules) + ['is_payroll' => $payroll];
    }
}

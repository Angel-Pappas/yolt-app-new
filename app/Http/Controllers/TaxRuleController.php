<?php

namespace App\Http\Controllers;

use App\Models\TaxRule;
use App\Support\Recalculate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration ▸ Tax rules: the dated sets of tax-law figures every calculation
 * uses (social insurance, the payroll tax scale, company income tax, the severance
 * cap). A change in the law is a **new set from its date** — started as a copy of the
 * one before it — so everything dated earlier keeps the old law. Saving recalculates
 * whatever is not yet reconciled.
 */
class TaxRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tax-rules/index', [
            'sets' => TaxRule::query()->orderByDesc('valid_from')->get()
                ->map(fn (TaxRule $r): array => [
                    'id' => $r->id,
                    'valid_from' => $r->valid_from->format('Y-m-d'),
                    'note' => $r->note,
                    'data' => $r->data,
                ]),
        ]);
    }

    /** A new set from a date, starting as a copy of the set in force just before it. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'valid_from' => ['required', 'date', self::freeDate(null)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $source = TaxRule::query()->whereDate('valid_from', '<=', $data['valid_from'])->orderByDesc('valid_from')->first()
            ?? TaxRule::query()->orderBy('valid_from')->firstOrFail();

        DB::transaction(function () use ($data, $source) {
            TaxRule::create(['valid_from' => $data['valid_from'], 'note' => $data['note'] ?? null, 'data' => $source->data]);
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rules added — now edit what changed.')]);

        return back();
    }

    public function update(Request $request, TaxRule $taxRule): RedirectResponse
    {
        $validated = $request->validate([
            'valid_from' => ['required', 'date', self::freeDate($taxRule)],
            'note' => ['nullable', 'string', 'max:255'],
            'data.efka.employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'data.efka.employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'data.efka.monthly_cap' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.salaries_per_year' => ['required', 'numeric', 'min:1', 'max:20'],
            'data.payroll_tax.brackets' => ['required', 'array', 'min:1'],
            'data.payroll_tax.brackets.*.up_to' => ['nullable', 'numeric', 'min:0'],
            'data.payroll_tax.brackets.*.rates' => ['required', 'array', 'size:5'],
            'data.payroll_tax.brackets.*.rates.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'data.payroll_tax.age_bands' => ['present', 'array'],
            'data.payroll_tax.age_bands.*.max_age' => ['required', 'integer', 'min:0', 'max:120'],
            'data.payroll_tax.age_bands.*.up_to' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.age_bands.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'data.payroll_tax.reduction.by_children' => ['required', 'array', 'size:5'],
            'data.payroll_tax.reduction.by_children.*' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.reduction.extra_per_child' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.reduction.threshold' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.reduction.cut_per_1000' => ['required', 'numeric', 'min:0'],
            'data.payroll_tax.reduction.no_cut_from_children' => ['required', 'integer', 'min:0'],
            'data.company_tax.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'data.company_tax.advance_rate' => ['required', 'numeric', 'min:0', 'max:200'],
            'data.company_tax.business_levy' => ['required', 'numeric', 'min:0'],
            'data.company_tax.installments' => ['required', 'integer', 'min:1', 'max:12'],
            'data.company_tax.first_installment_month' => ['required', 'integer', 'min:1', 'max:12'],
            'data.severance.min_daily_wage' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($taxRule, $validated) {
            $taxRule->update([
                'valid_from' => $validated['valid_from'],
                'note' => $validated['note'] ?? null,
                'data' => self::numeric($validated['data']),
            ]);
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rules saved.')]);

        return back();
    }

    public function destroy(TaxRule $taxRule): RedirectResponse
    {
        abort_if(TaxRule::query()->count() <= 1, 422, __('The last set of rules cannot be deleted.'));

        DB::transaction(function () use ($taxRule) {
            $taxRule->delete();
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rules deleted.')]);

        return back();
    }

    /** No two sets may start on the same date (whereDate: the column may hold a time). */
    private static function freeDate(?TaxRule $ignore): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignore): void {
            $taken = TaxRule::query()
                ->whereDate('valid_from', (string) $value)
                ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();
            if ($taken) {
                $fail(__('There is already a set of rules from that date.'));
            }
        };
    }

    /**
     * Store numbers as numbers (form input arrives as strings); a blank bracket limit
     * stays null ("and above").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function numeric(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value): void {
            if ($value !== null && $value !== '') {
                $value = (float) $value;
            } elseif ($value === '') {
                $value = null;
            }
        });

        return $data;
    }
}

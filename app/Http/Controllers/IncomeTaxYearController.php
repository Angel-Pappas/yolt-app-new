<?php

namespace App\Http\Controllers;

use App\Models\IncomeTaxYear;
use App\Support\IncomeTaxLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Income tax — the one stored, manually-entered tax. Each fiscal year records its
 * revenue, total tax and installment window; the generated schedule (one installment
 * per month, due that month's last working day) feeds the Taxes views. Available to
 * any active user; shared company data with a created-by `user_id`.
 */
class IncomeTaxYearController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('taxes/income', [
            'years' => IncomeTaxYear::query()
                ->orderByDesc('year')
                ->get([
                    'id',
                    'year',
                    'revenue_before_tax',
                    'total_tax',
                    'first_installment_month',
                    'last_installment_month',
                    'monthly_installment_amount',
                ]),
            'schedule' => IncomeTaxLedger::schedule(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $record = new IncomeTaxYear($this->validated($request));
        $record->user_id = $request->user()->id;
        $record->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income tax year created.')]);

        return back();
    }

    public function update(Request $request, IncomeTaxYear $incomeTaxYear): RedirectResponse
    {
        $incomeTaxYear->update($this->validated($request, $incomeTaxYear));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income tax year updated.')]);

        return back();
    }

    public function destroy(IncomeTaxYear $incomeTaxYear): RedirectResponse
    {
        $incomeTaxYear->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income tax year deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?IncomeTaxYear $ignore = null): array
    {
        $data = $request->validate([
            'year' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('income_tax_years', 'year')
                    ->whereNull('deleted_at')
                    ->ignore($ignore?->id),
            ],
            'revenue_before_tax' => ['required', 'numeric', 'min:0'],
            'total_tax' => ['required', 'numeric', 'min:0'],
            'first_installment_month' => ['required', 'date'],
            'last_installment_month' => ['required', 'date', 'after_or_equal:first_installment_month'],
            'monthly_installment_amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Normalise the installment months to the first of the month — only the
        // year-month matters, and the ledger walks month by month.
        $data['first_installment_month'] = Carbon::parse($data['first_installment_month'])->startOfMonth()->toDateString();
        $data['last_installment_month'] = Carbon::parse($data['last_installment_month'])->startOfMonth()->toDateString();

        return $data;
    }
}

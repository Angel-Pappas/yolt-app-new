<?php

namespace App\Http\Controllers;

use App\Models\WithheldTaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Withheld (withholding) tax rates CRUD — mirrors VAT rates. Gated by
 * `can:access-finance`; shared company data with a created-by `user_id` field.
 */
class WithheldTaxRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('withheld-tax-rates/index', [
            'withheldRates' => WithheldTaxRate::query()
                ->orderBy('rate')
                ->get(['id', 'name', 'rate']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rate = new WithheldTaxRate($this->validateRate($request));
        $rate->user_id = $request->user()->id;
        $rate->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Withheld tax rate created.')]);

        return back();
    }

    public function update(Request $request, WithheldTaxRate $withheldTaxRate): RedirectResponse
    {
        $withheldTaxRate->update($this->validateRate($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Withheld tax rate updated.')]);

        return back();
    }

    public function destroy(WithheldTaxRate $withheldTaxRate): RedirectResponse
    {
        $withheldTaxRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Withheld tax rate deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}

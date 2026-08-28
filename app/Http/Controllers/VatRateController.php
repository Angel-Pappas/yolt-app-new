<?php

namespace App\Http\Controllers;

use App\Models\VatRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * VAT rates CRUD. Gated by `can:access-finance`; shared company data with a
 * created-by `user_id` audit field.
 */
class VatRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('vat-rates/index', [
            'vatRates' => VatRate::query()
                ->orderBy('rate')
                ->get(['id', 'name', 'rate']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rate = new VatRate($this->validateRate($request));
        $rate->user_id = $request->user()->id;
        $rate->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VAT rate created.')]);

        return back();
    }

    public function update(Request $request, VatRate $vatRate): RedirectResponse
    {
        $vatRate->update($this->validateRate($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VAT rate updated.')]);

        return back();
    }

    public function destroy(VatRate $vatRate): RedirectResponse
    {
        $vatRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VAT rate deleted.')]);

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

<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\RecurringIncome;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\RecurringIncomeLines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * A customer's recurring income (rare): kept on the customer's page, it creates
 * the customer's monthly payment lines ({@see RecurringIncomeLines}).
 */
class RecurringIncomeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $income = new RecurringIncome($this->validateIncome($request));
        $income->user_id = $request->user()->id;
        DB::transaction(function () use ($income): void {
            $income->save();
            RecurringIncomeLines::sync($income);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring income added.')]);

        return back();
    }

    public function update(Request $request, RecurringIncome $recurringIncome): RedirectResponse
    {
        DB::transaction(function () use ($request, $recurringIncome): void {
            $recurringIncome->update($this->validateIncome($request));
            RecurringIncomeLines::sync($recurringIncome);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring income updated.')]);

        return back();
    }

    public function destroy(RecurringIncome $recurringIncome): RedirectResponse
    {
        DB::transaction(function () use ($recurringIncome): void {
            $recurringIncome->delete();
            RecurringIncomeLines::sync($recurringIncome);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring income deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncome(Request $request): array
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', Rule::exists(Entity::class, 'id')->where('type', 'customer')->whereNull('deleted_at')],
            'wallet_id' => ['required', 'integer', Rule::exists(Wallet::class, 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'income')->whereNull('deleted_at')],
            'vat_rate_id' => ['nullable', 'integer', Rule::exists(VatRate::class, 'id')],
            'description' => ['nullable', 'string', 'max:255'],
            'net' => ['required', 'numeric', 'gt:0'],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $data['description'] = $data['description'] ?? '';
        $data['net'] = round((float) $data['net'], 2);

        return $data;
    }
}

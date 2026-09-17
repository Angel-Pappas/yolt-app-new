<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Categories CRUD. Available to any active user; shared company data with a
 * created-by `user_id` audit field. Each category is tied to income or expense and
 * carries an optional description. A category can be opened on its own page to edit
 * it and to mass-recategorise/delete its transactions. A category is only ever
 * removed once nothing uses it, so it is **hard-deleted** (not soft-deleted).
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('categories/index', [
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'description']),
        ]);
    }

    /**
     * A category's own page: the edit form plus every transaction filed under it,
     * with the sibling categories of the same type offered as recategorise targets.
     * The transactions carry their full shape (VAT/withholding lines included) and
     * the finance lookups ride along so a row can be opened in the edit dialog.
     */
    public function show(Category $category): Response
    {
        $transactions = Transaction::query()
            ->where('category_id', $category->id)
            ->with([
                'wallet:id,name',
                'toWallet:id,name',
                'entity:id,name',
                'category:id,name',
                'vatLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'vat_rate_id', 'position'),
                'withheldLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'withheld_rate_id', 'position'),
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return Inertia::render('categories/show', [
            'category' => $category->only(['id', 'name', 'type', 'description']),
            'transactions' => $transactions,
            // Same-type siblings are the valid targets for a bulk recategorise.
            'targets' => Category::query()
                ->where('type', $category->type)
                ->where('id', '!=', $category->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            // Lookups for the transaction edit dialog opened from a row.
            'wallets' => Wallet::query()->orderBy('name')->get(['id', 'name']),
            'entities' => Entity::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
            'vatRates' => VatRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
            'withheldRates' => WithheldTaxRate::query()->orderBy('rate')->get(['id', 'name', 'rate']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['income', 'expense', 'both'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // "both" creates a matching income and expense category in one go — two
        // independent rows, not linked in any way.
        $types = $data['type'] === 'both' ? ['income', 'expense'] : [$data['type']];

        foreach ($types as $type) {
            $category = new Category([
                'name' => $data['name'],
                'type' => $type,
                'description' => $data['description'] ?? null,
            ]);
            $category->user_id = $request->user()->id;
            $category->save();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($types) > 1 ? __('Categories created.') : __('Category created.'),
        ]);

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validateCategory($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return back();
    }

    /**
     * Delete a category — but only if nothing still uses it. A category in use by
     * any live (non-trashed) transaction can't be deleted; the user must first
     * recategorise those transactions away from it (see the category page's bulk
     * actions). Once empty, it is removed for good rather than kept as a soft-delete.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->transactions()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This category is still used by transactions — move them to another category first.'),
            ]);

            return back();
        }

        $category->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RatePeriod;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;
use App\Support\Recalculate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A VAT or withholding rate's own page: its dated changes ("from this date the rate
 * is X %"). The rate's own percentage is what applies before the first change. Saving
 * a change recalculates everything not yet reconciled that it affects.
 *
 * One controller for both kinds; the route's `{kind}` slug says which.
 */
class RateChangeController extends Controller
{
    /** URL slug → what it is. */
    public const KINDS = [
        'vat-rates' => ['type' => 'vat', 'model' => VatRate::class, 'title' => 'VAT rates'],
        'withheld-tax-rates' => ['type' => 'withheld', 'model' => WithheldTaxRate::class, 'title' => 'Withheld tax'],
    ];

    public function show(string $kind, int $rate): Response
    {
        $model = $this->rate($kind, $rate);
        $type = self::KINDS[$kind]['type'];

        return Inertia::render('rates/show', [
            'rate' => ['id' => $model->getKey(), 'name' => $model->getAttribute('name'), 'rate' => $model->getAttribute('rate')],
            'changes' => RatePeriod::query()
                ->where('rate_type', $type)
                ->where('rate_id', $rate)
                ->orderBy('valid_from')
                ->get(['id', 'valid_from', 'rate'])
                ->map(fn (RatePeriod $p): array => [
                    'id' => $p->id,
                    'valid_from' => $p->valid_from->format('Y-m-d'),
                    'rate' => $p->rate,
                ]),
            'baseUrl' => "/configuration/{$kind}",
            'listTitle' => self::KINDS[$kind]['title'],
        ]);
    }

    public function store(Request $request, string $kind, int $rate): RedirectResponse
    {
        $this->rate($kind, $rate);
        $type = self::KINDS[$kind]['type'];
        $data = $this->validateChange($request, $type, $rate);

        DB::transaction(function () use ($type, $rate, $data) {
            RatePeriod::create(['rate_type' => $type, 'rate_id' => $rate] + $data);
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate change added.')]);

        return back();
    }

    public function update(Request $request, string $kind, int $rate, RatePeriod $change): RedirectResponse
    {
        $type = self::KINDS[$kind]['type'];
        $this->owns($change, $type, $rate);
        $data = $this->validateChange($request, $type, $rate, $change);

        DB::transaction(function () use ($change, $data) {
            $change->update($data);
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate change updated.')]);

        return back();
    }

    public function destroy(string $kind, int $rate, RatePeriod $change): RedirectResponse
    {
        $this->owns($change, self::KINDS[$kind]['type'], $rate);

        DB::transaction(function () use ($change) {
            $change->delete();
            Recalculate::everything();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate change deleted.')]);

        return back();
    }

    private function rate(string $kind, int $rate): Model
    {
        return self::KINDS[$kind]['model']::query()->findOrFail($rate);
    }

    private function owns(RatePeriod $change, string $type, int $rate): void
    {
        abort_unless($change->rate_type === $type && $change->rate_id === $rate, 404);
    }

    /**
     * @return array{valid_from: string, rate: float}
     */
    private function validateChange(Request $request, string $type, int $rate, ?RatePeriod $ignore = null): array
    {
        $data = $request->validate([
            'valid_from' => [
                'required', 'date',
                // whereDate, not a unique rule: the column may hold a time part.
                function (string $attribute, mixed $value, \Closure $fail) use ($type, $rate, $ignore): void {
                    $taken = RatePeriod::query()
                        ->where('rate_type', $type)->where('rate_id', $rate)
                        ->whereDate('valid_from', (string) $value)
                        ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
                        ->exists();
                    if ($taken) {
                        $fail(__('This rate already has a change on that date.'));
                    }
                },
            ],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        return ['valid_from' => (string) $data['valid_from'], 'rate' => round((float) $data['rate'], 2)];
    }
}

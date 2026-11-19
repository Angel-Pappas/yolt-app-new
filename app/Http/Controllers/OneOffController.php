<?php

namespace App\Http\Controllers;

use App\Models\PlannedTransaction;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\TransactionWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning ▸ One-offs: expenses expected once — planned rows
 * ({@see PlannedTransaction}, `is_one_off`) made with the same form as a
 * transaction. A one-off is done once real rows carrying its key (matched at the
 * bank, or typed with "Pays planned item") cover it; the page shows the open ones
 * unless "Show all", done ones in green.
 */
class OneOffController extends TransactionController
{
    public function index(Request $request): Response
    {
        $items = Plan::items()->filter(fn (PlanItem $i): bool => $i->kind === 'one-off');
        $rows = PlannedTransaction::query()->withListData()->where('is_one_off', true)->orderBy('date')->get();

        return Inertia::render('one-offs/index', [
            'transactions' => $rows->map(function (PlannedTransaction $t) use ($items): array {
                $item = $items->get($t->planKey());

                return [
                    ...$t->toArray(),
                    'paid' => $item !== null ? $item->paid : 0.0,
                    'open' => $item !== null ? $item->open() : 0.0,
                    'done' => $item === null || $item->isDone(),
                    'overdue' => $item !== null && $item->overdue,
                ];
            })->values(),
            'editId' => $request->integer('edit') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTransaction($request);
        $line = new PlannedTransaction;
        $line->user_id = $request->user()->id;
        $line->is_one_off = true;
        TransactionWriter::persist($line, [...$data, 'type' => 'expense']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('One-off planned.')]);

        return back();
    }

    public function updateOneOff(Request $request, PlannedTransaction $oneOff): RedirectResponse
    {
        abort_unless($oneOff->is_one_off, 404);
        TransactionWriter::persist($oneOff, [...$this->validateTransaction($request), 'type' => 'expense']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('One-off updated.')]);

        return back();
    }

    public function excludeOneOff(Request $request, PlannedTransaction $oneOff): RedirectResponse
    {
        abort_unless($oneOff->is_one_off, 404);
        $data = $request->validate(['excluded' => ['required', 'boolean']]);
        $oneOff->forceFill(['is_excluded' => (bool) $data['excluded']])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['excluded'] ? __('One-off excluded.') : __('One-off included.')]);

        return back();
    }

    public function destroyOneOff(PlannedTransaction $oneOff): RedirectResponse
    {
        abort_unless($oneOff->is_one_off, 404);
        $oneOff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('One-off deleted.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PlanAdjustment;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Support\Plan;
use App\Support\PlanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * What to do with a planned item nothing paid (Bank import ▸ Not matched yet):
 * **link** it to the real transaction that was its payment, **move** it to the day
 * it is now expected, or **cancel** it (it won't happen). A computed item (a
 * recurrence occurrence, a tax payment) keeps the move or cancel as an adjustment;
 * a planned row (one-off, payment line) is simply moved or deleted.
 */
class PlanItemController extends Controller
{
    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string'], 'transaction_id' => ['required', 'integer']]);
        $item = $this->item($data['key']);
        $row = Transaction::query()->findOrFail((int) $data['transaction_id']);
        if ($row->managed_key !== null) {
            throw ValidationException::withMessages(['transaction_id' => __('That transaction already pays a planned item.')]);
        }
        $row->forceFill([
            'managed_key' => $item->key,
            'source' => in_array($item->kind, ['recurrence', 'tax'], true) ? $item->kind : null,
            'recurrence_id' => $item->template->recurrence_id,
        ])->save();

        return $this->done(__('Linked.'));
    }

    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string'], 'date' => ['required', 'date']]);
        $item = $this->item($data['key']);
        $planned = $item->planned() !== null ? PlannedTransaction::query()->findOrFail($item->planned()->id) : null;
        if ($planned !== null) {
            $fill = ['date' => $data['date']];
            if (! $item->invoiceFixed()) {
                $fill['invoice_date'] = $data['date'];
            }
            $planned->forceFill($fill)->save();
        } else {
            PlanAdjustment::query()->updateOrCreate(['plan_key' => $item->key], ['date' => $data['date'], 'cancelled' => false, 'user_id' => $request->user()->id]);
        }

        return $this->done(__('Moved.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);
        $item = $this->item($data['key']);
        if ($item->planned() !== null) {
            if ($item->paid > 0) {
                throw ValidationException::withMessages(['key' => __('Part of it is paid: lower its amount to what was paid instead.')]);
            }
            PlannedTransaction::query()->findOrFail($item->planned()->id)->delete();
        } else {
            PlanAdjustment::query()->updateOrCreate(['plan_key' => $item->key], ['cancelled' => true, 'user_id' => $request->user()->id]);
        }

        return $this->done(__('Cancelled.'));
    }

    private function item(string $key): PlanItem
    {
        return Plan::find($key) ?? throw ValidationException::withMessages(['key' => __('That planned item is no longer open.')]);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

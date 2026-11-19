<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\TransactionWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * A customer's payment lines — the income the company expects (Angel, 2026-11-19).
 * A line is a planned row ({@see PlannedTransaction}, type income): payment date,
 * invoice date, net · VAT · total at one VAT rate (24% by default, 0% abroad), the
 * wallet it lands in, and an **Issued** mark. Paid is never stored: a line is paid
 * once real rows carrying its key cover it (matched at the bank).
 *
 * The invoice date follows the payment date in the form until the invoice is marked
 * issued (or part of the line is paid) — then it stays put, still editable by hand.
 * A part-paid line can't be deleted: lower its amount to what was paid instead.
 */
class PaymentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $line = new PlannedTransaction;
        $line->user_id = $request->user()->id;
        $this->write($line, $this->validateLine($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment added.')]);

        return back();
    }

    public function update(Request $request, PlannedTransaction $payment): RedirectResponse
    {
        abort_unless($payment->type === 'income', 404);
        $payment->is_edited = true;
        $this->write($payment, $this->validateLine($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment updated.')]);

        return back();
    }

    /** Mark the invoice issued (its date then stays put) or not. */
    public function issued(Request $request, PlannedTransaction $payment): RedirectResponse
    {
        abort_unless($payment->type === 'income', 404);
        $data = $request->validate(['issued' => ['required', 'boolean']]);
        $payment->forceFill(['is_issued' => (bool) $data['issued'], 'is_edited' => true])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['issued'] ? __('Marked as issued.') : __('Marked as not issued.')]);

        return back();
    }

    /** Switch a line off (left out of the projection and taxes) or on. */
    public function exclude(Request $request, PlannedTransaction $payment): RedirectResponse
    {
        abort_unless($payment->type === 'income', 404);
        $data = $request->validate(['excluded' => ['required', 'boolean']]);
        $payment->forceFill(['is_excluded' => (bool) $data['excluded']])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['excluded'] ? __('Payment excluded.') : __('Payment included.')]);

        return back();
    }

    public function destroy(PlannedTransaction $payment): RedirectResponse
    {
        abort_unless($payment->type === 'income', 404);
        if ((Plan::find($payment->planKey())->paid ?? 0) > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Part of this payment is already paid: set its amount to what was paid instead.')]);

            return back();
        }
        $payment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment deleted.')]);

        return back();
    }

    /**
     * Payment lines as the lists show them (Income tab, a customer's Payments):
     * the line, what is paid and open, and whether it sits in a concluded period
     * unpaid.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(?int $entityId = null): array
    {
        return array_values(Plan::items()
            ->filter(fn (PlanItem $i): bool => $i->kind === 'payment'
                && ($entityId === null || $i->template->entity_id === $entityId))
            ->map(fn (PlanItem $i): array => [
                ...$i->toArray(),
                'vat_rate_id' => $i->template->vat_rate_id,
                'is_issued' => (bool) $i->template->is_issued,
                'invoice_fixed' => $i->invoiceFixed(),
                'recurring' => $i->template->recurring_income_id !== null,
                'done' => $i->isDone(),
            ])
            ->sortBy(fn (array $r): string => $r['date'].'|'.$r['key'])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(PlannedTransaction $line, array $data): void
    {
        TransactionWriter::persist($line, [
            'type' => 'income',
            'date' => $data['date'],
            'invoice_date' => $data['invoice_date'],
            'description' => $data['description'] ?? '',
            'entity_id' => $data['entity_id'],
            'category_id' => $data['category_id'] ?? $line->category_id,
            'wallet_id' => $data['wallet_id'],
            'amount_mode' => $data['amount_mode'],
            'lines' => [['amount' => $data['amount'], 'vat_rate_id' => $data['vat_rate_id'] ?? null, 'withheld_rate_id' => null]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLine(Request $request): array
    {
        return $request->validate([
            'entity_id' => ['required', 'integer', Rule::exists(Entity::class, 'id')->where('type', 'customer')->whereNull('deleted_at')],
            'wallet_id' => ['required', 'integer', Rule::exists(Wallet::class, 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'income')->whereNull('deleted_at')],
            'date' => ['required', 'date'],
            'invoice_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'amount_mode' => ['required', Rule::in(['net', 'total'])],
            'vat_rate_id' => ['nullable', 'integer', Rule::exists(VatRate::class, 'id')],
        ], ['entity_id.exists' => __('Pick a customer.')]);
    }
}

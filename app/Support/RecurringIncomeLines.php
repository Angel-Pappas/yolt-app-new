<?php

namespace App\Support;

use App\Models\PlannedTransaction;
use App\Models\RecurringIncome;
use Illuminate\Support\Carbon;

/**
 * The payment lines a customer's recurring income creates: one a month on its day
 * (clamped to short months), from its start through its end or 24 months ahead,
 * each keyed by its month (`recurring_slot`). Re-running it after a change rebuilds
 * only the untouched lines: one edited by hand, issued, or already (part-)paid is
 * left exactly as it is. Deleting the recurring income removes its untouched lines.
 */
final class RecurringIncomeLines
{
    public static function sync(RecurringIncome $income): void
    {
        $desired = $income->trashed() ? [] : self::slots($income);

        $existing = PlannedTransaction::query()->where('recurring_income_id', $income->id)->get()
            ->keyBy('recurring_slot');

        foreach ($desired as $slot => $date) {
            $line = $existing->pull($slot);
            if ($line !== null && self::touched($line)) {
                continue;
            }
            $line ??= new PlannedTransaction;
            $line->user_id = $income->user_id;
            $line->forceFill(['recurring_income_id' => $income->id, 'recurring_slot' => $slot]);
            TransactionWriter::persist($line, [
                'type' => 'income',
                'date' => $date,
                'invoice_date' => $date,
                'description' => $income->description,
                'entity_id' => $income->entity_id,
                'category_id' => $income->category_id,
                'wallet_id' => $income->wallet_id,
                'amount_mode' => 'net',
                'lines' => [['amount' => (float) $income->net, 'vat_rate_id' => $income->vat_rate_id, 'withheld_rate_id' => null]],
            ]);
        }

        foreach ($existing as $line) {
            if (! self::touched($line)) {
                $line->delete();
            }
        }
    }

    /** A line someone acted on — kept as it is. */
    private static function touched(PlannedTransaction $line): bool
    {
        return $line->is_edited || $line->is_issued || $line->invoice_pinned
            || (Plan::find($line->planKey())->paid ?? 0) > 0;
    }

    /**
     * Month 'Y-m' → its payment date.
     *
     * @return array<string, string>
     */
    private static function slots(RecurringIncome $income): array
    {
        $horizon = Carbon::today()->addMonths(RecurrenceGenerator::HORIZON_MONTHS);
        $end = $income->end_date !== null && $income->end_date->lessThan($horizon) ? $income->end_date : $horizon;

        $slots = [];
        for ($m = $income->start_date->startOfMonth(); $m->lessThanOrEqualTo($end); $m = $m->addMonth()) {
            $date = $m->day(min($income->day_of_month, $m->daysInMonth));
            if ($date->lessThan($income->start_date) || $date->greaterThan($end)) {
                continue;
            }
            $slots[$m->format('Y-m')] = $date->toDateString();
        }

        return $slots;
    }
}

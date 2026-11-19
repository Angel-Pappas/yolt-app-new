<?php

namespace App\Support;

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Planning ▸ Recurring: every recurring expense as one row, grouped by who it is
 * with — payroll (an employee), contractors, and other expenses — showing
 * the amounts of the period that matters now, the recurrence's date range, and
 * whether it is done.
 *
 * Amounts resolve through {@see AmountLines::resolve()} (the same resolver the
 * plan uses), so a row shows exactly what its next occurrence moves. (Recurring
 * income lives on the customer, as payment lines.)
 */
class RecurringOverview
{
    public const GROUPS = ['payroll', 'contractors', 'expenses'];

    /**
     * @return list<array{id: int, group: string, entity: array{id: int, name: string, type: string}, recurrence: array<string, mixed>, description: string, active: bool, excluded: bool, done: bool, start_date: string, end_date: string|null, net: float, vat: float, withheld: float, fmy: float, efka_employee: float, efka_employer: float, total: float, cost: float}>
     */
    public static function rows(): array
    {
        $recurrences = Recurrence::query()
            ->with(['entity:id,name,type', 'entity.employeeProfile', 'entries.lines'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        // The recurrences that still have an occurrence open in the plan.
        $open = Plan::nonTaxItems()
            ->filter(fn (PlanItem $i): bool => $i->kind === 'recurrence' && ! $i->isDone())
            ->map(fn (PlanItem $i): ?int => $i->template->recurrence_id)
            ->flip();

        return array_values($recurrences
            ->map(fn (Recurrence $r): array => self::row($r, $open->has($r->id)))
            ->all());
    }

    /**
     * A recurrence is **done** once it has an end date and nothing of it is still
     * open in the plan (every occurrence paid or cancelled). An open-ended
     * recurrence is never done. The page hides done ones unless "Show all".
     */
    public static function isDone(Recurrence $recurrence, bool $hasOpen): bool
    {
        return $recurrence->end_date !== null && ! $hasOpen;
    }

    /** Which group a recurrence belongs to, by the entity's type. */
    public static function group(Recurrence $recurrence): string
    {
        return match ($recurrence->entity?->type) {
            'employee' => 'payroll',
            'contractor' => 'contractors',
            default => 'expenses',
        };
    }

    /**
     * The period whose amounts the row shows: the one in force today; else the next
     * one to start (a recurrence that hasn't begun); else the last one (it has ended).
     */
    public static function currentEntry(Recurrence $recurrence): ?RecurrenceEntry
    {
        $today = Carbon::today();

        return RecurrenceGenerator::entryFor($recurrence, $today)
            ?? $recurrence->entries->first(fn (RecurrenceEntry $e) => $e->start_date->greaterThan($today))
            ?? $recurrence->entries->last();
    }

    /**
     * @return array{id: int, group: string, entity: array{id: int, name: string, type: string}, recurrence: array<string, mixed>, description: string, active: bool, excluded: bool, done: bool, start_date: string, end_date: string|null, net: float, vat: float, withheld: float, fmy: float, efka_employee: float, efka_employer: float, total: float, cost: float}
     */
    private static function row(Recurrence $recurrence, bool $hasOpen): array
    {
        $amounts = self::amounts(self::currentEntry($recurrence), $recurrence->is_payroll, $recurrence);

        return [
            'id' => $recurrence->id,
            'group' => self::group($recurrence),
            'entity' => [
                'id' => $recurrence->entity_id,
                'name' => $recurrence->entity->name ?? '—',
                'type' => $recurrence->entity->type ?? '',
            ],
            // The full record (periods and lines), for the shared edit form.
            'recurrence' => Arr::except($recurrence->toArray(), ['entity']),
            'description' => $recurrence->description,
            'active' => $recurrence->active,
            'excluded' => $recurrence->is_excluded,
            'done' => self::isDone($recurrence, $hasOpen),
            'start_date' => $recurrence->start_date->toDateString(),
            'end_date' => $recurrence->end_date?->toDateString(),
            ...$amounts,
        ];
    }

    /**
     * One occurrence's money. `total` is the cash it moves, as on a transaction
     * (net + VAT − withheld − FMY − EFKA employee); `cost` adds the employer's EFKA
     * (what an employee costs the company). Mirrors the generator: a payroll period
     * (or one with no lines) takes Net/FMY/EFKA straight from the period.
     *
     * @return array{net: float, vat: float, withheld: float, fmy: float, efka_employee: float, efka_employer: float, total: float, cost: float}
     */
    public static function amounts(?RecurrenceEntry $entry, bool $payroll, ?Recurrence $recurrence = null): array
    {
        $net = $vat = $withheld = $fmy = $efkaEmployee = $efkaEmployer = 0.0;

        if ($entry !== null && ! $payroll && $entry->lines->isNotEmpty()) {
            $resolved = AmountLines::resolve(
                $entry->lines->map(fn (RecurrenceEntryLine $l) => [
                    'amount' => $l->amount,
                    'vat_rate_id' => $l->vat_rate_id,
                    'withheld_rate_id' => $l->withheld_rate_id,
                ])->all(),
                $entry->amount_mode,
                Carbon::today(),
            );
            $net = $resolved['net'];
            $vat = $resolved['vat_amount'];
            $withheld = $resolved['withheld_amount'];
        } elseif ($entry !== null) {
            // Typed figures, or (a period set to automatic) today's calculation.
            $payrollAmounts = $recurrence !== null
                ? RecurrenceGenerator::payrollAmounts($recurrence, $entry, Carbon::today())
                : ['net' => (float) $entry->net, 'fmy_amount' => $entry->fmy_amount, 'efka_employee_amount' => $entry->efka_employee_amount, 'efka_employer_amount' => $entry->efka_employer_amount];
            $net = (float) $payrollAmounts['net'];
            $fmy = (float) ($payrollAmounts['fmy_amount'] ?? 0);
            $efkaEmployee = (float) ($payrollAmounts['efka_employee_amount'] ?? 0);
            $efkaEmployer = (float) ($payrollAmounts['efka_employer_amount'] ?? 0);
        }

        return [
            'net' => $net,
            'vat' => $vat,
            'withheld' => $withheld,
            'fmy' => $fmy,
            'efka_employee' => $efkaEmployee,
            'efka_employer' => $efkaEmployer,
            'total' => round($net + $vat - $withheld - $fmy - $efkaEmployee, 2),
            'cost' => round($net + $efkaEmployer, 2),
        ];
    }
}

<?php

namespace App\Support;

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * The Finance ▸ Recurring page: every recurrence as one row, grouped by who it is
 * with — payroll (an employee), contractors, other expenses, and income — showing
 * the amounts of the period that matters now, the recurrence's date range, and
 * whether it is done.
 *
 * Amounts resolve through {@see AmountLines::resolve()} (the same resolver the
 * generator uses), so a row shows exactly what its next generated transaction moves.
 */
class RecurringOverview
{
    public const GROUPS = ['payroll', 'contractors', 'expenses', 'income'];

    /**
     * @return list<array{id: int, group: string, entity: array{id: int, name: string, type: string}, recurrence: array<string, mixed>, description: string, active: bool, done: bool, start_date: string, end_date: string|null, net: float, vat: float, withheld: float, fmy: float, efka_employee: float, efka_employer: float, total: float, cost: float}>
     */
    public static function rows(): array
    {
        $recurrences = Recurrence::query()
            ->with(['entity:id,name,type', 'entries.lines'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        // Whether each recurrence's last generated transaction is reconciled.
        $lastReconciled = Transaction::query()
            ->whereIn('recurrence_id', $recurrences->modelKeys())
            ->orderBy('date')
            ->orderBy('id')
            ->get(['recurrence_id', 'is_reconciled'])
            ->keyBy('recurrence_id')
            ->map(fn (Transaction $t): bool => $t->is_reconciled);

        return array_values($recurrences
            ->map(fn (Recurrence $r): array => self::row($r, $lastReconciled->get($r->id)))
            ->all());
    }

    /**
     * A recurrence is **done** once it has an end date and its last generated
     * transaction has been reconciled (or it never generated one). An open-ended
     * recurrence is never done. The page hides done ones unless "Show all".
     */
    public static function isDone(Recurrence $recurrence, ?bool $lastReconciled): bool
    {
        return $recurrence->end_date !== null && ($lastReconciled ?? true);
    }

    /**
     * Which group a recurrence belongs to. Income is its own group whatever the
     * entity; an expense goes by the entity's type.
     */
    public static function group(Recurrence $recurrence): string
    {
        if ($recurrence->type === 'income') {
            return 'income';
        }

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
     * @return array{id: int, group: string, entity: array{id: int, name: string, type: string}, recurrence: array<string, mixed>, description: string, active: bool, done: bool, start_date: string, end_date: string|null, net: float, vat: float, withheld: float, fmy: float, efka_employee: float, efka_employer: float, total: float, cost: float}
     */
    private static function row(Recurrence $recurrence, ?bool $lastReconciled): array
    {
        $amounts = self::amounts(self::currentEntry($recurrence), $recurrence->is_payroll);

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
            'done' => self::isDone($recurrence, $lastReconciled),
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
    public static function amounts(?RecurrenceEntry $entry, bool $payroll): array
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
            );
            $net = $resolved['net'];
            $vat = $resolved['vat_amount'];
            $withheld = $resolved['withheld_amount'];
        } elseif ($entry !== null) {
            $net = (float) $entry->net;
            $fmy = (float) ($entry->fmy_amount ?? 0);
            $efkaEmployee = (float) ($entry->efka_employee_amount ?? 0);
            $efkaEmployer = (float) ($entry->efka_employer_amount ?? 0);
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

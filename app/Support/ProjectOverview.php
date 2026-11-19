<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * The money summary of a project, derived live from its installments (its income
 * transactions) — never stored:
 *
 *  - `scheduled`: the net of every installment that still stands (not dropped);
 *  - `invoiced`: of those, the ones tagged invoiced or paid;
 *  - `paid`: the ones tagged paid;
 *  - `outstanding`: scheduled − paid;
 *  - `unscheduled`: the agreed total not yet covered by an installment (when an
 *    agreed total is set);
 *  - `next_due`: the date of the earliest installment not yet paid;
 *  - `done`: it has installments and none is still open (all paid or dropped).
 *
 * Amounts are net (before VAT), like the agreed total.
 */
class ProjectOverview
{
    /**
     * @param  Collection<int, Transaction>  $installments
     * @return array{installments: int, scheduled: float, invoiced: float, paid: float, outstanding: float, unscheduled: float|null, next_due: string|null, done: bool}
     */
    public static function summarize(Project $project, Collection $installments): array
    {
        $standing = $installments->filter(fn (Transaction $t) => $t->income_status !== 'dropped');
        $sum = fn (Collection $rows): float => round((float) $rows->sum(fn (Transaction $t) => (float) $t->net), 2);

        $scheduled = $sum($standing);
        $paid = $sum($standing->filter(fn (Transaction $t) => $t->income_status === 'paid'));
        $open = $standing->filter(fn (Transaction $t) => $t->income_status !== 'paid');

        return [
            'installments' => $installments->count(),
            'scheduled' => $scheduled,
            'invoiced' => $sum($standing->filter(fn (Transaction $t) => in_array($t->income_status, ['invoiced', 'paid'], true))),
            'paid' => $paid,
            'outstanding' => round($scheduled - $paid, 2),
            'unscheduled' => $project->agreed_total !== null
                ? round((float) $project->agreed_total - $scheduled, 2)
                : null,
            'next_due' => $open->min(fn (Transaction $t) => $t->date->toDateString()),
            'done' => $installments->isNotEmpty() && $open->isEmpty(),
        ];
    }
}

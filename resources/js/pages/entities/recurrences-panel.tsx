import { router } from '@inertiajs/react';
import { Plus, Repeat, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { sumLines } from '@/components/transactions/amount-lines';
import {
    type FinanceLookups,
    useFinanceLookups,
} from '@/components/transactions/lookups';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatAmount } from '@/lib/format';
import {
    type RecurrenceEntryRecord,
    type RecurrenceRecord,
    RecurrenceFormDialog,
} from './recurrence-form-dialog';

type Props = {
    entityId: number;
    entityName: string;
    entityType: string | null;
    recurrences: RecurrenceRecord[];
};

function cadence(r: RecurrenceRecord): string {
    const unit = `${r.interval_unit}${r.interval_count > 1 ? 's' : ''}`;
    const base = `Every ${r.interval_count} ${unit}`;
    return r.interval_unit === 'week' || r.day_of_month == null
        ? base
        : `${base} · day ${r.day_of_month}`;
}

/** What one period moves per occurrence: a payroll period's net, else the cash
 *  total of its lines (net + VAT − withheld), as the transaction form shows it. */
function periodAmount(
    r: RecurrenceRecord,
    e: RecurrenceEntryRecord,
    lookups: FinanceLookups,
): number {
    if (r.is_payroll || e.lines.length === 0) return Number(e.net);
    return sumLines(
        e.lines.map((l) => ({
            amount: l.amount,
            vat_rate_id: l.vat_rate_id ? String(l.vat_rate_id) : '',
            withheld: l.withheld_rate_id != null,
            withheld_rate_id: l.withheld_rate_id
                ? String(l.withheld_rate_id)
                : '',
        })),
        e.amount_mode === 'total' ? 'total' : 'net',
        lookups.vatRates,
        lookups.withheldRates,
    ).total;
}

/** The amount currently in force (today) — the latest dated period on/before today,
 *  so an open-ended earlier period doesn't shadow a later one. */
function currentAmount(r: RecurrenceRecord, lookups: FinanceLookups): string {
    const today = new Date().toISOString().slice(0, 10);
    const inForce = r.entries
        .filter(
            (e) =>
                e.start_date.slice(0, 10) <= today &&
                (e.end_date == null || e.end_date.slice(0, 10) >= today),
        )
        .sort((a, b) => a.start_date.localeCompare(b.start_date))
        .at(-1);
    const entry = inForce ?? r.entries[r.entries.length - 1];
    return entry ? formatAmount(periodAmount(r, entry, lookups)) : '—';
}

export function RecurrencesPanel({
    entityId,
    entityName,
    entityType,
    recurrences,
}: Props) {
    const lookups = useFinanceLookups();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<RecurrenceRecord | null>(null);
    const [formKey, setFormKey] = useState(0);

    const defaultType = entityType === 'customer' ? 'income' : 'expense';
    const defaultPayroll = entityType === 'employee';

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    function openEdit(r: RecurrenceRecord) {
        setEditing(r);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    function destroy(r: RecurrenceRecord) {
        if (
            confirm(
                'Delete this recurring transaction? Its unreconciled generated transactions will be removed.',
            )
        ) {
            router.delete(`/recurrences/${r.id}`, { preserveScroll: true });
        }
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <Repeat className="size-4" /> Recurring transactions
                </CardTitle>
                <Button size="sm" onClick={openCreate}>
                    <Plus className="size-4" /> Add
                </Button>
            </CardHeader>
            <CardContent>
                {recurrences.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No recurring transactions yet. Add one to auto-generate
                        its transactions.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {recurrences.map((r) => (
                            <li
                                key={r.id}
                                className="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-4 py-3"
                                onClick={() => openEdit(r)}
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {r.description || 'Recurring'}
                                        </span>
                                        {!r.active && (
                                            <Badge variant="secondary">
                                                Paused
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {cadence(r)}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="font-medium tabular-nums">
                                        {currentAmount(r, lookups)}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete recurring transaction"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            destroy(r);
                                        }}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            <RecurrenceFormDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
                entityId={entityId}
                entityName={entityName}
                defaultType={defaultType}
                defaultPayroll={defaultPayroll}
                editing={editing}
            />
        </Card>
    );
}

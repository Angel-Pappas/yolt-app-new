import { type Page } from '@inertiajs/core';
import { Head, useRemember } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable, DONE_ROW_CLASS } from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { deleteRecurrence } from '@/components/recurrences/delete-recurrence';
import {
    type RecurrenceRecord,
    RecurrenceFormDialog,
} from '@/components/recurrences/recurrence-form-dialog';
import { type EntityOption } from '@/components/transactions/lookups';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
import {
    defaultGroup,
    groupCounts,
    RECURRING_GROUPS,
    type RecurringGroup,
} from '@/lib/recurring';
import { cn } from '@/lib/utils';

/** One recurrence, as `App\Support\RecurringOverview` sends it. */
type Row = {
    id: number;
    group: RecurringGroup;
    entity: EntityOption;
    /** The full record, for the edit form. */
    recurrence: RecurrenceRecord;
    description: string;
    active: boolean;
    /** Has an end date and its last transaction is reconciled — hidden unless "Show all". */
    done: boolean;
    start_date: string;
    end_date: string | null;
    net: number;
    vat: number;
    withheld: number;
    fmy: number;
    efka_employee: number;
    efka_employer: number;
    total: number;
    cost: number;
};

type MoneyKey = keyof Pick<
    Row,
    | 'net'
    | 'vat'
    | 'withheld'
    | 'fmy'
    | 'efka_employee'
    | 'efka_employer'
    | 'total'
    | 'cost'
>;

// The money columns each group shows. Total is the cash an occurrence moves (as
// on Transactions); payroll also shows its full cost to the company.
const MONEY_COLUMNS: Record<RecurringGroup, [MoneyKey, string][]> = {
    payroll: [
        ['net', 'Net'],
        ['fmy', 'FMY'],
        ['efka_employee', 'EFKA employee'],
        ['efka_employer', 'EFKA employer'],
        ['total', 'To pay'],
        ['cost', 'Total cost'],
    ],
    contractors: [
        ['net', 'Net'],
        ['vat', 'VAT'],
        ['withheld', 'Withheld'],
        ['total', 'Total'],
    ],
    expenses: [
        ['net', 'Net'],
        ['vat', 'VAT'],
        ['total', 'Total'],
    ],
    income: [
        ['net', 'Net'],
        ['vat', 'VAT'],
        ['total', 'Total'],
    ],
};

// The headline figure of each group is bold; the breakdown is muted.
const EMPHASIS: MoneyKey[] = ['total', 'cost'];

function moneyColumn(key: MoneyKey, title: string): ColumnDef<Row> {
    return {
        id: key,
        accessorFn: (row) => row[key],
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title={title} align="right" />
        ),
        cell: ({ row }) => (
            <span
                className={cn(
                    EMPHASIS.includes(key)
                        ? 'font-medium'
                        : key !== 'net' && 'text-muted-foreground',
                )}
            >
                {formatAmount(row.original[key])}
            </span>
        ),
        footer: ({ table }: { table: Table<Row> }) =>
            formatAmount(
                table
                    .getFilteredRowModel()
                    .rows.reduce((s, r) => s + r.original[key], 0),
            ),
    };
}

function columnsFor(group: RecurringGroup): ColumnDef<Row>[] {
    return [
        {
            id: 'entity',
            accessorFn: (row) => row.entity.name,
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.entity.name}</span>
            ),
            footer: ({ table }) => {
                const n = table.getFilteredRowModel().rows.length;
                return (
                    <span className="text-muted-foreground font-normal">
                        {n} {n === 1 ? 'row' : 'rows'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'description',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => (
                <span className="flex items-center gap-2">
                    <span className="text-muted-foreground">
                        {row.original.description || '—'}
                    </span>
                    {!row.original.active && (
                        <Badge variant="secondary">Paused</Badge>
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'start_date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="From" />
            ),
            cell: ({ row }) => formatDate(row.original.start_date),
        },
        {
            id: 'end_date',
            // Ongoing counts as ending after any date: it sorts last and a
            // date-range filter treats it that way.
            accessorFn: (row) => row.end_date ?? '9999-12-31',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => <ColumnHeader column={column} title="To" />,
            cell: ({ row }) =>
                row.original.end_date ? (
                    formatDate(row.original.end_date)
                ) : (
                    <span className="text-muted-foreground">Ongoing</span>
                ),
        },
        ...MONEY_COLUMNS[group].map(([key, title]) => moneyColumn(key, title)),
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <Button
                    variant="ghost"
                    size="icon"
                    // Above the row's stretched open-in-new-tab link.
                    className="relative z-10"
                    aria-label={`Delete ${row.original.entity.name}'s recurring transaction`}
                    onClick={(e) => {
                        e.stopPropagation();
                        deleteRecurrence(row.original.id);
                    }}
                >
                    <Trash2 className="size-4" />
                </Button>
            ),
        },
    ];
}

export default function RecurringIndex({
    recurrences,
    editId,
}: {
    recurrences: Row[];
    /** From `?edit=<id>`: open this recurrence's edit form on arrival. */
    editId: number | null;
}) {
    // The chosen group and "Show all" survive leaving the page and coming Back.
    // Only active recurrences show unless "Show all" (then done ones are green).
    const deepLinked = recurrences.find((r) => r.id === editId) ?? null;
    const [showAll, setShowAll] = useRemember(
        deepLinked?.done ?? false,
        'recurring-show-all',
    );
    const visible = showAll ? recurrences : recurrences.filter((r) => !r.done);
    const [group, setGroup] = useRemember<RecurringGroup>(
        deepLinked?.group ?? defaultGroup(visible),
        'recurring-group',
    );
    const counts = groupCounts(visible);
    const rows = visible.filter((r) => r.group === group);

    // The shared add/edit form. Adding starts with no entity (it's picked in the
    // form); editing keeps the row's entity. The `key` bump gives a fresh form.
    const [formOpen, setFormOpen] = useState(deepLinked !== null);
    const [editing, setEditing] = useState<Row | null>(deepLinked);
    const [formKey, setFormKey] = useState(0);

    function openForm(row: Row | null) {
        setEditing(row);
        setFormKey((k) => k + 1);
        setFormOpen(true);
    }

    // After a save, show the group the recurrence is now in — for a new one, the
    // row that wasn't there before.
    function followSaved(page: Page) {
        const next = (page.props as { recurrences?: Row[] }).recurrences ?? [];
        const saved = editing
            ? next.find((r) => r.id === editing.id)
            : next.find((r) => !recurrences.some((o) => o.id === r.id));
        if (saved) setGroup(saved.group);
    }

    return (
        <>
            <Head title="Recurring" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    // A fresh table per group: its columns differ, so a column
                    // filter from another group must not linger.
                    key={group}
                    columns={columnsFor(group)}
                    data={rows}
                    title="Recurring"
                    searchPlaceholder="Search recurring…"
                    emptyMessage="No recurring transactions here."
                    enableTotals
                    onRowClick={openForm}
                    rowHref={(r) => `/recurring?edit=${r.id}`}
                    rowClassName={(r) => (r.done ? DONE_ROW_CLASS : undefined)}
                    action={
                        <Button
                            onClick={() => openForm(null)}
                            size="icon"
                            aria-label="Add recurring transaction"
                            title="Add recurring transaction"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                    controls={
                        <div className="flex flex-wrap items-center gap-2">
                            {RECURRING_GROUPS.map((g) => (
                                <Button
                                    key={g.key}
                                    variant="outline"
                                    size="sm"
                                    aria-pressed={group === g.key}
                                    className={cn(
                                        group === g.key &&
                                            'text-primary border-primary',
                                    )}
                                    onClick={() => setGroup(g.key)}
                                >
                                    {g.label}
                                    <span className="text-muted-foreground tabular-nums">
                                        {counts[g.key]}
                                    </span>
                                </Button>
                            ))}
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                />
            </div>

            <RecurrenceFormDialog
                key={formKey}
                open={formOpen}
                onOpenChange={setFormOpen}
                entity={editing?.entity ?? null}
                editing={editing?.recurrence ?? null}
                onSaved={followSaved}
            />
        </>
    );
}

RecurringIndex.layout = {
    breadcrumbs: [{ title: 'Recurring', href: '/recurring' }],
};

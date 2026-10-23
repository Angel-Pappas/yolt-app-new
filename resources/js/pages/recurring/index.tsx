import { Head, router, useRemember } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
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
    entity_id: number;
    entity_name: string;
    description: string;
    active: boolean;
    /** Its last period ended before today. */
    ended: boolean;
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
            accessorKey: 'entity_name',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.entity_name}</span>
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
                    {row.original.ended ? (
                        <Badge variant="secondary">Ended</Badge>
                    ) : (
                        !row.original.active && (
                            <Badge variant="secondary">Paused</Badge>
                        )
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
    ];
}

export default function RecurringIndex({
    recurrences,
}: {
    recurrences: Row[];
}) {
    // The chosen group survives a trip to an entity's page and back.
    const [group, setGroup] = useRemember<RecurringGroup>(
        defaultGroup(recurrences),
        'recurring-group',
    );
    const counts = groupCounts(recurrences);
    const rows = recurrences.filter((r) => r.group === group);

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
                    onRowClick={(r) => router.visit(`/entities/${r.entity_id}`)}
                    rowHref={(r) => `/entities/${r.entity_id}`}
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
                        </div>
                    }
                />
            </div>
        </>
    );
}

RecurringIndex.layout = {
    breadcrumbs: [{ title: 'Recurring', href: '/recurring' }],
};

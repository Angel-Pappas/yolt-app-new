import { Head, router, useRemember } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import {
    type CrudField,
    CrudFormDialog,
} from '@/components/crud/crud-form-dialog';
import { ColumnHeader } from '@/components/data-table/column-header';
import {
    DataTable,
    DONE_ROW_CLASS,
    EXCLUDED_ROW_CLASS,
} from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { ExcludeToggle } from '@/components/exclude-toggle';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/** One project with its money summary (`App\Support\ProjectOverview`). */
type Row = {
    id: number;
    name: string;
    entity: { id: number; name: string };
    agreed_total: string | null;
    is_excluded: boolean;
    installments: number;
    scheduled: number;
    invoiced: number;
    paid: number;
    outstanding: number;
    unscheduled: number | null;
    next_due: string | null;
    /** Every installment is paid or dropped — hidden unless "Show all". */
    done: boolean;
};

type MoneyKey = 'scheduled' | 'invoiced' | 'paid' | 'outstanding';

function moneyColumn(
    key: MoneyKey,
    title: string,
    emphasis = false,
): ColumnDef<Row> {
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
                    emphasis ? 'font-medium' : 'text-muted-foreground',
                )}
            >
                {formatAmount(row.original[key])}
            </span>
        ),
        // Switched-off projects are listed but add nothing to the totals.
        footer: ({ table }: { table: Table<Row> }) =>
            formatAmount(
                table
                    .getFilteredRowModel()
                    .rows.filter((r) => !r.original.is_excluded)
                    .reduce((s, r) => s + r.original[key], 0),
            ),
    };
}

const columns: ColumnDef<Row>[] = [
    {
        accessorKey: 'name',
        meta: { filter: { type: 'text' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Project" />
        ),
        cell: ({ row }) => (
            <span className="font-medium">{row.original.name}</span>
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
        id: 'customer',
        accessorFn: (row) => row.entity.name,
        meta: { filter: { type: 'text' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Customer" />
        ),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {row.original.entity.name}
            </span>
        ),
    },
    {
        id: 'agreed',
        accessorFn: (row) =>
            row.agreed_total != null ? Number(row.agreed_total) : 0,
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Agreed" align="right" />
        ),
        cell: ({ row }) =>
            row.original.agreed_total != null
                ? formatAmount(row.original.agreed_total)
                : '—',
    },
    moneyColumn('scheduled', 'Scheduled'),
    moneyColumn('invoiced', 'Invoiced'),
    moneyColumn('paid', 'Paid'),
    moneyColumn('outstanding', 'Outstanding', true),
    {
        id: 'next_due',
        // No open installment sorts last.
        accessorFn: (row) => row.next_due ?? '9999-12-31',
        meta: { filter: { type: 'date' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Next due" />
        ),
        cell: ({ row }) =>
            row.original.next_due ? (
                <span className="whitespace-nowrap tabular-nums">
                    {formatDate(row.original.next_due)}
                </span>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        id: 'actions',
        enableSorting: false,
        meta: { align: 'right' },
        header: () => null,
        cell: ({ row }) => (
            // Above the row's stretched open-in-new-tab link.
            <div className="relative z-10 flex justify-end">
                <ExcludeToggle
                    url={`/projects/${row.original.id}/excluded`}
                    excluded={row.original.is_excluded}
                    label="project"
                />
            </div>
        ),
    },
];

/**
 * Finance ▸ Projects: the agreed work per customer, with what is scheduled,
 * invoiced, paid and still outstanding (net amounts, from each project's
 * installments). Only open projects show; "Show all" brings back the finished ones
 * in green. A row opens the project's own page.
 */
export default function ProjectsIndex({ projects }: { projects: Row[] }) {
    const { entities } = useFinanceLookups();
    const [showAll, setShowAll] = useRemember(false, 'projects-show-all');
    const [addOpen, setAddOpen] = useState(false);
    const [addKey, setAddKey] = useState(0);

    const rows = showAll ? projects : projects.filter((p) => !p.done);

    const fields: CrudField[] = [
        { key: 'name', label: 'Name', type: 'text', required: true },
        {
            key: 'entity_id',
            label: 'Customer',
            type: 'select',
            required: true,
            options: entities
                .filter((e) => e.type === 'customer')
                .map((e) => ({ value: String(e.id), label: e.name })),
            placeholder: 'Choose a customer',
            defaultValue: '',
        },
        {
            key: 'agreed_total',
            label: 'Agreed total, net (optional)',
            type: 'money',
        },
        { key: 'notes', label: 'Notes (optional)', type: 'textarea' },
    ];

    return (
        <>
            <Head title="Projects" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={rows}
                    title="Projects"
                    searchPlaceholder="Search projects…"
                    emptyMessage={
                        showAll ? 'No projects yet.' : 'No open projects.'
                    }
                    enableTotals
                    onRowClick={(p) => router.visit(`/projects/${p.id}`)}
                    rowHref={(p) => `/projects/${p.id}`}
                    rowClassName={(p) =>
                        cn(
                            p.is_excluded && EXCLUDED_ROW_CLASS,
                            p.done && DONE_ROW_CLASS,
                        )
                    }
                    controls={
                        <div className="flex">
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                    action={
                        <Button
                            onClick={() => {
                                setAddKey((k) => k + 1);
                                setAddOpen(true);
                            }}
                            size="icon"
                            aria-label="Add project"
                            title="Add project"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <CrudFormDialog
                key={addKey}
                open={addOpen}
                onOpenChange={setAddOpen}
                singular="project"
                baseUrl="/projects"
                fields={fields}
                editing={null}
                description="A project groups the payments agreed with a customer. Add its installments on the project's page."
            />
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};

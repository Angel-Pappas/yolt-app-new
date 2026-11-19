import { Head, router } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import {
    DataTable,
    DONE_ROW_CLASS,
    EXCLUDED_ROW_CLASS,
} from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { ExcludeToggle } from '@/components/exclude-toggle';
import { PageTabs, PLANNING_TABS } from '@/components/page-tabs';
import {
    type Transaction,
    transactionTotal as cashTotal,
} from '@/components/transactions/types';
import { Button } from '@/components/ui/button';
import { useStoredState } from '@/hooks/use-stored-state';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { TransactionFormDialog } from '@/pages/transactions/transaction-form-dialog';

type OneOff = Transaction & {
    paid: number;
    open: number;
    done: boolean;
    overdue: boolean;
};

const footer = (pick: (r: OneOff) => number) =>
    function Footer({ table }: { table: Table<OneOff> }) {
        return formatAmount(
            table
                .getFilteredRowModel()
                .rows.reduce((s, r) => s + pick(r.original), 0),
        );
    };

/**
 * Planning ▸ One-offs: expenses expected once. Each is part of the plan (it counts
 * in the projection and the taxes until paid), made with the usual transaction
 * form; it is done once the real payment — matched at the bank, or typed with
 * "Pays planned item" — covers it. Open ones show; "Show all" brings back the done
 * ones, in green.
 */
export default function OneOffsIndex({
    transactions,
    editId,
}: {
    transactions: OneOff[];
    editId: number | null;
}) {
    const [showAll, setShowAll] = useStoredState('one-offs-show-all', false);
    const linked = transactions.find((t) => t.id === editId) ?? null;
    const [formOpen, setFormOpen] = useState(linked !== null);
    const [formKey, setFormKey] = useState(0);
    const [editing, setEditing] = useState<OneOff | null>(linked);
    const rows = showAll ? transactions : transactions.filter((t) => !t.done);

    function openForm(row: OneOff | null) {
        setEditing(row);
        setFormKey((k) => k + 1);
        setFormOpen(true);
    }

    const columns: ColumnDef<OneOff>[] = [
        {
            id: 'date',
            accessorFn: (r) => r.date.slice(0, 10),
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {formatDate(row.original.date)}
                    {row.original.overdue && (
                        <span
                            className="ml-1 font-bold text-amber-600 dark:text-amber-400"
                            title="Its day is in a concluded period and nothing paid it — see Bank import ▸ Not matched yet"
                        >
                            !
                        </span>
                    )}
                </span>
            ),
        },
        {
            id: 'entity',
            accessorFn: (r) => r.entity?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => row.original.entity?.name ?? '—',
        },
        {
            id: 'category',
            accessorFn: (r) => r.category?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Category" />
            ),
            cell: ({ row }) => row.original.category?.name ?? '—',
        },
        {
            id: 'description',
            accessorFn: (r) => r.description,
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'net',
            accessorFn: (r) => Number(r.net),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
            footer: footer((r) => Number(r.net)),
        },
        {
            id: 'total',
            accessorFn: (r) => cashTotal(r),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Total" align="right" />
            ),
            cell: ({ row }) => formatAmount(cashTotal(row.original)),
            footer: footer((r) => cashTotal(r)),
        },
        {
            id: 'open',
            accessorFn: (r) => r.open,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title="Still to pay"
                    align="right"
                />
            ),
            cell: ({ row }) => formatAmount(row.original.open),
            footer: footer((r) => r.open),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="relative z-10 flex justify-end gap-0.5">
                        <ExcludeToggle
                            url={`/one-offs/${r.id}/excluded`}
                            excluded={r.is_excluded}
                            label="one-off"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Delete one-off"
                            title="Delete one-off"
                            onClick={() => {
                                if (confirm('Delete this one-off?')) {
                                    router.delete(`/one-offs/${r.id}`, {
                                        preserveScroll: true,
                                    });
                                }
                            }}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="One-offs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={rows}
                    title="One-offs"
                    heading={
                        <PageTabs
                            group="planning"
                            tabs={PLANNING_TABS}
                            active="/planning/one-offs"
                        />
                    }
                    searchPlaceholder="Search one-offs…"
                    emptyMessage={
                        showAll ? 'No one-offs yet.' : 'No open one-offs.'
                    }
                    enableTotals
                    onRowClick={openForm}
                    rowHref={(r) => `/planning/one-offs?edit=${r.id}`}
                    rowClassName={(r) =>
                        cn(
                            r.is_excluded && EXCLUDED_ROW_CLASS,
                            r.done && DONE_ROW_CLASS,
                        )
                    }
                    controls={
                        <div className="flex">
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                    action={
                        <Button
                            size="icon"
                            onClick={() => openForm(null)}
                            aria-label="Add one-off"
                            title="Add one-off"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>
            <TransactionFormDialog
                key={formKey}
                open={formOpen}
                onOpenChange={setFormOpen}
                editing={editing}
                oneOff
            />
        </>
    );
}

OneOffsIndex.layout = {
    breadcrumbs: [{ title: 'One-offs', href: '/planning/one-offs' }],
};

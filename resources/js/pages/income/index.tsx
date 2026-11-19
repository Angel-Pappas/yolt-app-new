import { Head } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { PageTabs, PLANNING_TABS } from '@/components/page-tabs';
import { PaymentFormDialog } from '@/components/payments/payment-form-dialog';
import {
    OWED_ROW_CLASS,
    PAID_ROW_CLASS,
    PaymentsTable,
} from '@/components/payments/payments-table';
import { type PaymentRow } from '@/components/payments/types';
import { Button } from '@/components/ui/button';
import { useStoredState } from '@/hooks/use-stored-state';
import { formatAmount, formatDate } from '@/lib/format';
import { byCustomer, isFromPastMonth } from '@/lib/income';
import { cn } from '@/lib/utils';

type View = 'list' | 'customers';
type CustomerGroup = ReturnType<typeof byCustomer<PaymentRow>>[number];

const heading = (
    <PageTabs group="planning" tabs={PLANNING_TABS} active="/planning/income" />
);

/**
 * Planning ▸ Income: what customers owe — every unpaid payment line, by payment
 * date, in green; one from an earlier month carries a "!" (chase it, move its
 * date). "Show all" adds the paid ones (muted). "By customer" lists the customers,
 * each opening to all its lines. Lines are added and edited here or on the
 * customer's page.
 */
export default function IncomeIndex({ payments }: { payments: PaymentRow[] }) {
    const [showAll, setShowAll] = useStoredState('income-show-all', false);
    const [view, setView] = useStoredState<View>('income-view', 'list');
    const rows = showAll ? payments : payments.filter((p) => !p.done);

    const controls = (
        <div className="flex flex-wrap items-center gap-2">
            {(
                [
                    ['list', 'By date'],
                    ['customers', 'By customer'],
                ] as [View, string][]
            ).map(([key, label]) => (
                <Button
                    key={key}
                    variant="outline"
                    size="sm"
                    aria-pressed={view === key}
                    className={cn(
                        view === key && 'text-primary border-primary',
                    )}
                    onClick={() => setView(key)}
                >
                    {label}
                </Button>
            ))}
            <ShowAllToggle on={showAll} onChange={setShowAll} />
        </div>
    );

    return (
        <>
            <Head title="Income" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {view === 'list' ? (
                    <PaymentsTable
                        rows={rows}
                        heading={heading}
                        title="Income"
                        stateKey="income-list"
                        controls={controls}
                        emptyMessage={
                            showAll ? 'No payments planned.' : 'Nothing owed.'
                        }
                    />
                ) : (
                    <CustomersView rows={rows} controls={controls} />
                )}
            </div>
        </>
    );
}

function CustomersView({
    rows,
    controls,
}: {
    rows: PaymentRow[];
    controls: React.ReactNode;
}) {
    const today = new Date().toISOString().slice(0, 10);
    const groups = byCustomer(rows);
    const [editing, setEditing] = useState<PaymentRow | null>(null);
    const [formKey, setFormKey] = useState(0);
    const [open, setOpen] = useState(false);

    const columns: ColumnDef<CustomerGroup>[] = [
        {
            id: 'customer',
            accessorFn: (g) => g.name,
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Customer" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'lines',
            accessorFn: (g) => g.rows.length,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Payments" align="right" />
            ),
        },
        {
            id: 'next',
            accessorFn: (g) => g.rows.find((r) => !r.done)?.date ?? '',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Next due" />
            ),
            cell: ({ row }) => {
                const next = row.original.rows.find((r) => !r.done);
                return next ? formatDate(next.date) : '—';
            },
        },
        {
            id: 'owed',
            accessorFn: (g) => g.owed,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Owed" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.owed),
            footer: ({ table }) =>
                formatAmount(
                    table
                        .getFilteredRowModel()
                        .rows.reduce((s, r) => s + r.original.owed, 0),
                ),
        },
    ];

    return (
        <>
            <DataTable
                columns={columns}
                data={groups}
                title="Income by customer"
                heading={heading}
                stateKey="income-customers"
                searchPlaceholder="Search customers…"
                emptyMessage="Nothing owed."
                enableTotals
                controls={controls}
                renderExpanded={(g) => (
                    <ul className="divide-y text-sm">
                        {g.rows.map((r) => (
                            <li key={r.key}>
                                <button
                                    type="button"
                                    className={cn(
                                        'hover:bg-muted grid w-full grid-cols-[7rem_7rem_1fr_7rem_7rem] gap-3 px-4 py-2 text-left tabular-nums',
                                        r.done
                                            ? PAID_ROW_CLASS
                                            : OWED_ROW_CLASS,
                                    )}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setEditing(r);
                                        setFormKey((k) => k + 1);
                                        setOpen(true);
                                    }}
                                >
                                    <span>
                                        {formatDate(r.date)}
                                        {!r.done &&
                                            !r.excluded &&
                                            isFromPastMonth(r.date, today) && (
                                                <span className="ml-1 font-bold text-amber-600 dark:text-amber-400">
                                                    !
                                                </span>
                                            )}
                                    </span>
                                    <span>
                                        {formatDate(r.invoice_date)}
                                        {r.is_issued && ' · issued'}
                                    </span>
                                    <span className="truncate">
                                        {r.description || '—'}
                                    </span>
                                    <span className="text-right">
                                        {formatAmount(r.total)}
                                    </span>
                                    <span className="text-right">
                                        {r.done
                                            ? 'paid'
                                            : `${formatAmount(r.open)} owed`}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            />
            <PaymentFormDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
                editing={editing}
            />
        </>
    );
}

IncomeIndex.layout = {
    breadcrumbs: [{ title: 'Income', href: '/planning/income' }],
};

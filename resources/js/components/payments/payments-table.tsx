import { router } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { FileCheck, Plus, Repeat, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import {
    DataTable,
    EXCLUDED_ROW_CLASS,
} from '@/components/data-table/data-table';
import { ExcludeToggle } from '@/components/exclude-toggle';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
import { isFromPastMonth } from '@/lib/income';
import { cn } from '@/lib/utils';
import { PaymentFormDialog } from './payment-form-dialog';
import { type PaymentRow } from './types';

/** Owed to us: a happy green (Angel). */
export const OWED_ROW_CLASS =
    '[&>td:not(:last-child)]:text-emerald-700 dark:[&>td:not(:last-child)]:text-emerald-400 [&_.text-muted-foreground]:text-inherit';
/** Paid: muted, so the owed ones stand out. */
export const PAID_ROW_CLASS = '[&>td:not(:last-child)]:text-muted-foreground';

const sum = (key: 'net' | 'vat' | 'total' | 'open') =>
    function Footer({ table }: { table: Table<PaymentRow> }) {
        return formatAmount(
            table
                .getFilteredRowModel()
                .rows.reduce((s, r) => s + r.original[key], 0),
        );
    };

function money(
    id: 'net' | 'vat' | 'total' | 'open',
    title: string,
): ColumnDef<PaymentRow> {
    return {
        id,
        accessorFn: (r) => r[id],
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title={title} align="right" />
        ),
        cell: ({ row }) => formatAmount(row.original[id]),
        footer: sum(id),
    };
}

/**
 * Customer payment lines in the shared list: payment date (a "!" when it is from
 * an earlier month and still owed — time to chase the customer), invoice date
 * (with its Issued mark), what it is, the amounts and what is still open. Owed
 * lines are green; paid ones muted. A row opens its edit form; the row buttons
 * mark the invoice issued, switch the line off, or delete it.
 */
export function PaymentsTable({
    rows,
    entityId = null,
    heading,
    title = 'Payments',
    controls,
    emptyMessage = 'No payments planned.',
    stateKey,
}: {
    rows: PaymentRow[];
    /** On a customer's page: that customer (its column is left out, Add is fixed to it). */
    entityId?: number | null;
    heading?: ReactNode;
    title?: string;
    controls?: ReactNode;
    emptyMessage?: string;
    stateKey?: string;
}) {
    const today = new Date().toISOString().slice(0, 10);
    const [formOpen, setFormOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const [editing, setEditing] = useState<PaymentRow | null>(null);

    function openForm(row: PaymentRow | null) {
        setEditing(row);
        setFormKey((k) => k + 1);
        setFormOpen(true);
    }

    const columns: ColumnDef<PaymentRow>[] = [
        ...(entityId
            ? []
            : [
                  {
                      id: 'customer',
                      accessorFn: (r: PaymentRow) => r.entity,
                      meta: { filter: { type: 'select' as const } },
                      header: ({ column }) => (
                          <ColumnHeader column={column} title="Customer" />
                      ),
                  } as ColumnDef<PaymentRow>,
              ]),
        {
            id: 'date',
            accessorFn: (r) => r.date,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Payment date" />
            ),
            cell: ({ row }) => {
                const r = row.original;
                const chase =
                    !r.done && !r.excluded && isFromPastMonth(r.date, today);
                return (
                    <span className="inline-flex items-center gap-1 tabular-nums">
                        {formatDate(r.date)}
                        {chase && (
                            <span
                                className="font-bold text-amber-600 dark:text-amber-400"
                                title="Owed since an earlier month — contact the customer and move the date"
                            >
                                !
                            </span>
                        )}
                    </span>
                );
            },
        },
        {
            id: 'invoice_date',
            accessorFn: (r) => r.invoice_date,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Invoice date" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 tabular-nums">
                    {formatDate(row.original.invoice_date)}
                    {row.original.is_issued && (
                        <span
                            className="text-xs font-medium"
                            title="The invoice was issued"
                        >
                            · issued
                        </span>
                    )}
                </span>
            ),
        },
        {
            id: 'description',
            accessorFn: (r) => r.description,
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1">
                    {row.original.recurring && (
                        <Repeat
                            className="size-3.5 shrink-0"
                            aria-label="From the recurring income"
                        />
                    )}
                    {row.original.description || '—'}
                </span>
            ),
        },
        money('net', 'Net'),
        money('vat', 'VAT'),
        money('total', 'Total'),
        money('open', 'Owed'),
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="relative z-10 flex justify-end gap-0.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-pressed={r.is_issued}
                            title={
                                r.is_issued
                                    ? 'Invoice issued — mark as not issued'
                                    : 'Mark the invoice as issued'
                            }
                            aria-label="Invoice issued"
                            onClick={() =>
                                router.patch(
                                    `/payments/${r.id}/issued`,
                                    { issued: !r.is_issued },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <FileCheck className="size-4" />
                        </Button>
                        <ExcludeToggle
                            url={`/payments/${r.id}/excluded`}
                            excluded={r.excluded}
                            label="payment"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            title={
                                r.paid > 0
                                    ? 'Part of it is paid — lower its amount instead'
                                    : 'Delete payment'
                            }
                            aria-label="Delete payment"
                            disabled={r.paid > 0}
                            onClick={() => {
                                if (confirm('Delete this payment?')) {
                                    router.delete(`/payments/${r.id}`, {
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
            <DataTable
                columns={columns}
                data={rows}
                title={title}
                heading={heading}
                stateKey={stateKey}
                searchPlaceholder="Search payments…"
                emptyMessage={emptyMessage}
                enableTotals
                controls={controls}
                onRowClick={openForm}
                rowClassName={(r) =>
                    cn(
                        r.excluded
                            ? EXCLUDED_ROW_CLASS
                            : r.done
                              ? PAID_ROW_CLASS
                              : OWED_ROW_CLASS,
                    )
                }
                action={
                    <Button
                        size="icon"
                        onClick={() => openForm(null)}
                        aria-label="Add payment"
                        title="Add payment"
                    >
                        <Plus className="size-4" />
                    </Button>
                }
            />
            <PaymentFormDialog
                key={formKey}
                open={formOpen}
                onOpenChange={setFormOpen}
                editing={editing}
                entityId={entityId}
            />
        </>
    );
}

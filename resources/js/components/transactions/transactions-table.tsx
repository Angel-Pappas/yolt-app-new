import { router } from '@inertiajs/react';
import { type ColumnDef, type Table } from '@tanstack/react-table';
import { CircleCheck, FileText, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { InvoiceDialog } from '@/pages/transactions/invoice-dialog';
import { ReconcileModal } from '@/pages/transactions/reconcile-modal';
import { TransactionFormDialog } from '@/pages/transactions/transaction-form-dialog';
import { useFinanceLookups } from './lookups';
import { DescriptionButton, DescriptionDialog } from './description-cell';
import {
    TransactionTypeIcon,
    transactionTypeOptions,
} from './transaction-type-icon';
import { type Transaction, transactionTotal } from './types';

/** Which per-row action buttons to show beside the always-present ⓘ description. */
type RowActions = {
    reconcile?: boolean;
    invoice?: boolean;
    delete?: boolean;
};

type Props = {
    transactions: Transaction[];
    /** Balance view: a running-Balance column replaces Wallet; Balance isn't summed. */
    balanceMode?: boolean;
    /** Extra per-row buttons (main list only, by default none). */
    rowActions?: RowActions;
    /** Row selection + a bulk-actions bar (e.g. the Category page). */
    enableSelection?: boolean;
    renderBulkActions?: (
        selected: Transaction[],
        clear: () => void,
    ) => ReactNode;
    // DataTable chrome — omit what a given page doesn't need.
    title?: string;
    searchPlaceholder?: string;
    controls?: ReactNode;
    toolbar?: ReactNode;
    action?: ReactNode;
    emptyMessage?: string;
    pageSize?: number;
    /** Totals footer (Net/VAT/Total sums + a row count). Default on. */
    showTotals?: boolean;
    /** From `?edit=<id>`: open this transaction's edit modal on mount. */
    editing?: Transaction | null;
};

/** Sum a numeric column over the currently-filtered rows, formatted as money. */
function sumFooter(id: string) {
    return ({ table }: { table: Table<Transaction> }) =>
        formatAmount(
            table
                .getFilteredRowModel()
                .rows.reduce((s, r) => s + Number(r.getValue(id)), 0),
        );
}

/** The invoice-button's 1–13 value for a transaction (13 = "no invoice needed"). */
function invoiceValue(t: Transaction): string {
    if (t.invoice_not_required) return '13';
    return t.invoice_month != null ? String(t.invoice_month) : '';
}

/**
 * The one transactions table used everywhere a list of transactions appears — the
 * Transactions page, a Category page, each Tax page, and any future place. It owns
 * the columns, the coloured type icons, the ⓘ description, the totals footer, and —
 * critically — row-click-to-edit plus a stretched "open in new tab" link, so editing
 * a transaction is possible from wherever you see it. Lookups for the edit form come
 * from the globally-shared source, so a page only supplies the transactions to show.
 */
export function TransactionsTable({
    transactions,
    balanceMode = false,
    rowActions = {},
    enableSelection = false,
    renderBulkActions,
    title,
    searchPlaceholder,
    controls,
    toolbar,
    action,
    emptyMessage = 'No transactions yet.',
    pageSize = 50,
    showTotals = true,
    editing = null,
}: Props) {
    const { wallets, entities, categories } = useFinanceLookups();

    // Edit modal — opened by a row click (or the `?edit=` deep-link on mount).
    const [editTx, setEditTx] = useState<Transaction | null>(editing);
    const [editOpen, setEditOpen] = useState(Boolean(editing));
    const [editKey, setEditKey] = useState(0);
    // The ⓘ description, per-row reconcile, and per-row invoice modals.
    const [descTx, setDescTx] = useState<Transaction | null>(null);
    const [descOpen, setDescOpen] = useState(false);
    const [descKey, setDescKey] = useState(0);
    const [reconTx, setReconTx] = useState<Transaction | null>(null);
    const [reconOpen, setReconOpen] = useState(false);
    const [reconKey, setReconKey] = useState(0);
    const [invTx, setInvTx] = useState<Transaction | null>(null);
    const [invOpen, setInvOpen] = useState(false);
    const [invKey, setInvKey] = useState(0);

    function openEdit(t: Transaction) {
        setEditTx(t);
        setEditKey((k) => k + 1);
        setEditOpen(true);
    }

    function handleEditOpenChange(open: boolean) {
        setEditOpen(open);
        // Drop `?edit=` when closing so a refresh doesn't reopen it.
        if (!open && typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit')) {
                url.searchParams.delete('edit');
                window.history.replaceState({}, '', url);
            }
        }
    }

    function openDescription(t: Transaction) {
        setDescTx(t);
        setDescKey((k) => k + 1);
        setDescOpen(true);
    }

    function openReconcile(t: Transaction) {
        setReconTx(t);
        setReconKey((k) => k + 1);
        setReconOpen(true);
    }

    function openInvoice(t: Transaction) {
        setInvTx(t);
        setInvKey((k) => k + 1);
        setInvOpen(true);
    }

    function destroy(t: Transaction) {
        if (confirm('Delete this transaction?')) {
            router.delete(`/transactions/${t.id}`, { preserveScroll: true });
        }
    }

    const walletOptions = wallets.map((w) => ({
        value: w.name,
        label: w.name,
    }));
    const categoryOptions = categories.map((c) => ({
        value: c.name,
        label: c.name,
    }));
    const entityOptions = entities.map((e) => ({
        value: e.name,
        label: e.name,
    }));

    const columns: ColumnDef<Transaction>[] = [
        {
            accessorKey: 'type',
            meta: {
                filter: { type: 'select', options: transactionTypeOptions },
            },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Type" />
            ),
            cell: ({ row }) => <TransactionTypeIcon type={row.original.type} />,
        },
        {
            accessorKey: 'date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap tabular-nums">
                    {formatDate(row.original.date)}
                </span>
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
            accessorKey: 'invoice_date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Invoice date" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap tabular-nums">
                    {formatDate(row.original.invoice_date)}
                </span>
            ),
        },
        ...(balanceMode
            ? []
            : [
                  {
                      id: 'wallet',
                      accessorFn: (row: Transaction) => row.wallet?.name ?? '',
                      meta: {
                          filter: {
                              type: 'select' as const,
                              options: walletOptions,
                          },
                      },
                      header: ({ column }) => (
                          <ColumnHeader column={column} title="Wallet" />
                      ),
                      cell: ({ row }) =>
                          row.original.type === 'transfer' ? (
                              <div className="leading-tight">
                                  <div>{row.original.wallet?.name ?? '—'}</div>
                                  <div className="text-muted-foreground text-xs">
                                      → {row.original.to_wallet?.name ?? '—'}
                                  </div>
                              </div>
                          ) : (
                              (row.original.wallet?.name ?? '—')
                          ),
                  } as ColumnDef<Transaction>,
              ]),
        {
            id: 'category',
            accessorFn: (row) => row.category?.name ?? '',
            meta: { filter: { type: 'select', options: categoryOptions } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Category" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.category?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'entity',
            accessorFn: (row) =>
                row.type === 'transfer' ? 'Transfer' : (row.entity?.name ?? ''),
            meta: { filter: { type: 'select', options: entityOptions } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.type === 'transfer'
                        ? 'Transfer'
                        : (row.original.entity?.name ?? '—')}
                </span>
            ),
        },
        {
            id: 'net',
            accessorFn: (row) => Number(row.net),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
            footer: sumFooter('net'),
        },
        {
            id: 'vat',
            accessorFn: (row) => Number(row.vat_amount),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="VAT" align="right" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatAmount(row.original.vat_amount)}
                </span>
            ),
            footer: sumFooter('vat'),
        },
        {
            id: 'total',
            accessorFn: (row) => transactionTotal(row),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Total" align="right" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(transactionTotal(row.original))}
                </span>
            ),
            footer: sumFooter('total'),
        },
        ...(balanceMode
            ? [
                  {
                      id: 'balance',
                      accessorFn: (row: Transaction) =>
                          row.balance != null ? Number(row.balance) : 0,
                      meta: { align: 'right' as const },
                      header: ({ column }) => (
                          <ColumnHeader
                              column={column}
                              title="Balance"
                              align="right"
                          />
                      ),
                      cell: ({ row }) =>
                          row.original.balance != null ? (
                              <span className="font-medium">
                                  {formatAmount(row.original.balance)}
                              </span>
                          ) : (
                              '—'
                          ),
                  } as ColumnDef<Transaction>,
              ]
            : []),
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => {
                const t = row.original;
                return (
                    <div
                        className="relative z-10 flex justify-end gap-1"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <DescriptionButton
                            description={t.description}
                            onClick={() => openDescription(t)}
                        />
                        {rowActions.reconcile && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => openReconcile(t)}
                                aria-label="Reconcile transaction"
                                aria-pressed={t.is_reconciled}
                                className={cn(
                                    t.is_reconciled &&
                                        'text-emerald-600 dark:text-emerald-500',
                                )}
                            >
                                <CircleCheck className="size-4" />
                            </Button>
                        )}
                        {rowActions.invoice && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => openInvoice(t)}
                                aria-label="Set invoice folder"
                                className={cn(
                                    (t.invoice_not_required ||
                                        t.invoice_month != null) &&
                                        'text-primary',
                                )}
                            >
                                <span className="relative inline-flex">
                                    <FileText className="size-4" />
                                    {t.invoice_month != null && (
                                        <span className="bg-primary text-primary-foreground absolute -top-1.5 -right-1.5 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full px-1 text-[9px] leading-none font-semibold tabular-nums">
                                            {t.invoice_month}
                                        </span>
                                    )}
                                    {t.invoice_not_required && (
                                        <span className="absolute top-1/2 left-1/2 h-[1.5px] w-5 -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-full bg-current" />
                                    )}
                                </span>
                            </Button>
                        )}
                        {rowActions.delete && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => destroy(t)}
                                aria-label="Delete transaction"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <DataTable
                columns={columns}
                data={transactions}
                title={title}
                searchPlaceholder={searchPlaceholder}
                emptyMessage={emptyMessage}
                pageSize={pageSize}
                controls={controls}
                toolbar={toolbar}
                action={action}
                onRowClick={openEdit}
                rowHref={(t) => `/transactions?edit=${t.id}`}
                enableTotals={showTotals}
                enableSelection={enableSelection}
                getRowId={(t) => String(t.id)}
                renderBulkActions={renderBulkActions}
            />

            <TransactionFormDialog
                key={editKey}
                open={editOpen}
                onOpenChange={handleEditOpenChange}
                editing={editTx}
            />

            <DescriptionDialog
                key={`desc-${descKey}`}
                transaction={descTx}
                open={descOpen}
                onOpenChange={setDescOpen}
            />

            {reconTx && (
                <ReconcileModal
                    key={`recon-${reconKey}`}
                    open={reconOpen}
                    onOpenChange={setReconOpen}
                    transaction={reconTx}
                    wallets={wallets}
                />
            )}

            {invTx && (
                <InvoiceDialog
                    key={`inv-${invKey}`}
                    open={invOpen}
                    onOpenChange={setInvOpen}
                    transactionId={invTx.id}
                    current={invoiceValue(invTx)}
                />
            )}
        </>
    );
}

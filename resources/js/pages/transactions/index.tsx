import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CircleCheck, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { BalanceViewControl } from './balance-view-control';
import { InvoiceDialog } from './invoice-dialog';
import {
    type EditableTransaction,
    TransactionFormDialog,
} from './transaction-form-dialog';
import {
    type TransactionFilters,
    TransactionsFilters,
} from './transactions-filters';

type Related = { id: number; name: string } | null;

type TransactionType = 'income' | 'expense' | 'transfer';

type Transaction = {
    id: number;
    date: string;
    invoice_date: string;
    description: string;
    type: TransactionType;
    net: string;
    vat_amount: string;
    withheld_amount: string;
    is_reconciled: boolean;
    invoice_month: number | null;
    invoice_not_required: boolean;
    entity_id: number | null;
    category_id: number | null;
    wallet_id: number;
    to_wallet_id: number | null;
    vat_rate_id: number | null;
    wallet: Related;
    to_wallet: Related;
    entity: Related;
    category: Related;
    vat_lines: { net: string; vat_rate_id: number | null }[];
    withheld_lines: { net: string; withheld_rate_id: number | null }[];
    // Present only in balance view: the running balance after this row.
    balance?: string | number;
};

type Option = { id: number; name: string };
type Category = { id: number; name: string; type: string };
type Rate = { id: number; name: string; rate: string };

type Props = {
    transactions: Transaction[];
    filters: TransactionFilters;
    balance: { wallet_id: number; wallet_name: string } | null;
    wallets: Option[];
    entities: Option[];
    categories: Category[];
    vatRates: Rate[];
    withheldRates: Rate[];
};

const typeMeta: Record<TransactionType, { label: string; className: string }> =
    {
        income: {
            label: 'Income',
            className: 'text-green-600 dark:text-green-500',
        },
        expense: {
            label: 'Expense',
            className: 'text-red-600 dark:text-red-500',
        },
        transfer: { label: 'Transfer', className: 'text-muted-foreground' },
    };

function total(t: Transaction): number {
    return Number(t.net) + Number(t.vat_amount) - Number(t.withheld_amount);
}

export default function TransactionsIndex({
    transactions,
    filters,
    balance,
    wallets,
    entities,
    categories,
    vatRates,
    withheldRates,
}: Props) {
    const balanceMode = balance !== null;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableTransaction | null>(null);
    // Bumped on every open so the reused dialog remounts with a fresh form.
    const [formKey, setFormKey] = useState(0);
    const [invoiceFor, setInvoiceFor] = useState<Transaction | null>(null);
    const [invoiceKey, setInvoiceKey] = useState(0);

    function toggleReconcile(t: Transaction) {
        router.post(
            `/transactions/${t.id}/reconcile`,
            {},
            { preserveScroll: true },
        );
    }

    function openInvoice(t: Transaction) {
        setInvoiceFor(t);
        setInvoiceKey((k) => k + 1);
    }

    function invoiceValue(t: Transaction): string {
        if (t.invoice_not_required) return '13';
        return t.invoice_month != null ? String(t.invoice_month) : '';
    }

    function invoiceLit(t: Transaction): boolean {
        return t.invoice_not_required || t.invoice_month != null;
    }

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function openEdit(t: Transaction) {
        setEditing(t);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function destroy(t: Transaction) {
        if (confirm('Delete this transaction?')) {
            router.delete(`/transactions/${t.id}`, { preserveScroll: true });
        }
    }

    const walletColumn: ColumnDef<Transaction> = {
        id: 'wallet',
        accessorFn: (row) => row.wallet?.name ?? '',
        header: ({ column }) => <ColumnHeader column={column} title="Wallet" />,
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
    };

    const balanceColumn: ColumnDef<Transaction> = {
        id: 'balance',
        accessorFn: (row) => (row.balance != null ? Number(row.balance) : 0),
        meta: { align: 'right' },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Balance" align="right" />
        ),
        cell: ({ row }) =>
            row.original.balance != null ? (
                <span className="font-medium">
                    {formatAmount(row.original.balance)}
                </span>
            ) : (
                '—'
            ),
    };

    const columns: ColumnDef<Transaction>[] = [
        {
            accessorKey: 'type',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'font-medium',
                        typeMeta[row.original.type].className,
                    )}
                >
                    {typeMeta[row.original.type].label}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap tabular-nums">
                    {formatDate(row.original.date)}
                </span>
            ),
        },
        ...(balanceMode ? [] : [walletColumn]),
        {
            id: 'category',
            accessorFn: (row) => row.category?.name ?? '',
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
            accessorKey: 'description',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'net',
            accessorFn: (row) => Number(row.net),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
        },
        {
            id: 'vat',
            accessorFn: (row) => Number(row.vat_amount),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader column={column} title="VAT" align="right" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatAmount(row.original.vat_amount)}
                </span>
            ),
        },
        {
            id: 'total',
            accessorFn: (row) => total(row),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Total" align="right" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(total(row.original))}
                </span>
            ),
        },
        ...(balanceMode ? [balanceColumn] : []),
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => {
                const t = row.original;
                return (
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => toggleReconcile(t)}
                            aria-label="Toggle reconciled"
                            aria-pressed={t.is_reconciled}
                            className={cn(
                                t.is_reconciled &&
                                    'text-emerald-600 dark:text-emerald-500',
                            )}
                        >
                            <CircleCheck className="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => openInvoice(t)}
                            aria-label="Set invoice folder"
                            className={cn(invoiceLit(t) && 'text-primary')}
                        >
                            <FileText className="size-4" />
                            {t.invoice_month != null && (
                                <span className="ml-0.5 text-xs tabular-nums">
                                    {t.invoice_month}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => openEdit(t)}
                            aria-label="Edit transaction"
                        >
                            <Pencil className="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => destroy(t)}
                            aria-label="Delete transaction"
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
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Transactions</h1>
                    <div className="flex items-center gap-2">
                        <BalanceViewControl
                            wallets={wallets}
                            filters={filters}
                            active={balance}
                        />
                        <Button
                            onClick={openCreate}
                            disabled={wallets.length === 0}
                        >
                            <Plus className="size-4" />
                            Add transaction
                        </Button>
                    </div>
                </div>

                <TransactionsFilters
                    filters={filters}
                    wallets={wallets}
                    hideWallet={balanceMode}
                />

                <DataTable
                    columns={columns}
                    data={transactions}
                    emptyMessage="No transactions yet."
                    pageSize={50}
                />
            </div>

            <TransactionFormDialog
                key={formKey}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editing={editing}
                wallets={wallets}
                entities={entities}
                categories={categories}
                vatRates={vatRates}
                withheldRates={withheldRates}
            />

            {invoiceFor && (
                <InvoiceDialog
                    key={invoiceKey}
                    open={invoiceFor !== null}
                    onOpenChange={(open) => !open && setInvoiceFor(null)}
                    transactionId={invoiceFor.id}
                    current={invoiceValue(invoiceFor)}
                />
            )}
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: '/transactions' }],
};

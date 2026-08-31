import { Head, router } from '@inertiajs/react';
import { CircleCheck, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Date</th>
                                {!balanceMode && (
                                    <th className="p-3 font-medium">Wallet</th>
                                )}
                                <th className="p-3 font-medium">Category</th>
                                <th className="p-3 font-medium">Entity</th>
                                <th className="p-3 font-medium">Description</th>
                                <th className="p-3 text-right font-medium">
                                    Net
                                </th>
                                <th className="p-3 text-right font-medium">
                                    VAT
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Total
                                </th>
                                {balanceMode && (
                                    <th className="p-3 text-right font-medium">
                                        Balance
                                    </th>
                                )}
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.map((t) => (
                                <tr key={t.id} className="border-t">
                                    <td
                                        className={`p-3 font-medium ${typeMeta[t.type].className}`}
                                    >
                                        {typeMeta[t.type].label}
                                    </td>
                                    <td className="text-muted-foreground p-3 whitespace-nowrap tabular-nums">
                                        {formatDate(t.date)}
                                    </td>
                                    {!balanceMode && (
                                        <td className="p-3">
                                            {t.type === 'transfer' ? (
                                                <div className="leading-tight">
                                                    <div>
                                                        {t.wallet?.name ?? '—'}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        →{' '}
                                                        {t.to_wallet?.name ??
                                                            '—'}
                                                    </div>
                                                </div>
                                            ) : (
                                                (t.wallet?.name ?? '—')
                                            )}
                                        </td>
                                    )}
                                    <td className="text-muted-foreground p-3">
                                        {t.category?.name ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {t.type === 'transfer'
                                            ? 'Transfer'
                                            : (t.entity?.name ?? '—')}
                                    </td>
                                    <td className="p-3">
                                        {t.description || '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(t.net)}
                                    </td>
                                    <td className="text-muted-foreground p-3 text-right tabular-nums">
                                        {formatAmount(t.vat_amount)}
                                    </td>
                                    <td className="p-3 text-right font-medium tabular-nums">
                                        {formatAmount(total(t))}
                                    </td>
                                    {balanceMode && (
                                        <td className="p-3 text-right font-medium tabular-nums">
                                            {t.balance != null
                                                ? formatAmount(t.balance)
                                                : '—'}
                                        </td>
                                    )}
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    toggleReconcile(t)
                                                }
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
                                                className={cn(
                                                    invoiceLit(t) &&
                                                        'text-primary',
                                                )}
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
                                    </td>
                                </tr>
                            ))}
                            {transactions.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No transactions yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
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

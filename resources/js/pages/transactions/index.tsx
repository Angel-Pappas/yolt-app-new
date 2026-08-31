import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatDate } from '@/lib/format';
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
    entity_id: number | null;
    category_id: number | null;
    wallet_id: number;
    to_wallet_id: number | null;
    vat_rate_id: number | null;
    wallet: Related;
    to_wallet: Related;
    entity: Related;
    category: Related;
    withheld_lines: { net: string; withheld_rate_id: number | null }[];
};

type Option = { id: number; name: string };
type Category = { id: number; name: string; type: string };
type Rate = { id: number; name: string; rate: string };

type Props = {
    transactions: Transaction[];
    filters: TransactionFilters;
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
    wallets,
    entities,
    categories,
    vatRates,
    withheldRates,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableTransaction | null>(null);
    // Bumped on every open so the reused dialog remounts with a fresh form.
    const [formKey, setFormKey] = useState(0);

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
                    <Button
                        onClick={openCreate}
                        disabled={wallets.length === 0}
                    >
                        <Plus className="size-4" />
                        Add transaction
                    </Button>
                </div>

                <TransactionsFilters filters={filters} wallets={wallets} />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Wallet</th>
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
                                    <td className="p-3">
                                        {t.type === 'transfer' ? (
                                            <div className="leading-tight">
                                                <div>
                                                    {t.wallet?.name ?? '—'}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    → {t.to_wallet?.name ?? '—'}
                                                </div>
                                            </div>
                                        ) : (
                                            (t.wallet?.name ?? '—')
                                        )}
                                    </td>
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
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
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
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: '/transactions' }],
};

import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';
import { Button } from '@/components/ui/button';
import { BalanceViewControl } from './balance-view-control';
import { TransactionFormDialog } from './transaction-form-dialog';
import {
    type TransactionFilters,
    TransactionsFilters,
} from './transactions-filters';

type Props = {
    transactions: Transaction[];
    filters: TransactionFilters;
    balance: { wallet_id: number; wallet_name: string } | null;
    /** Set by the `?edit=<id>` deep-link — opens that transaction's edit modal. */
    editing: Transaction | null;
};

export default function TransactionsIndex({
    transactions,
    filters,
    balance,
    editing,
}: Props) {
    const { wallets } = useFinanceLookups();
    const [addOpen, setAddOpen] = useState(false);
    // Bumped on every open so the reused dialog remounts with a fresh form.
    const [addKey, setAddKey] = useState(0);

    function openAdd() {
        setAddKey((k) => k + 1);
        setAddOpen(true);
    }

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TransactionsTable
                    transactions={transactions}
                    balanceMode={balance !== null}
                    rowActions={{
                        reconcile: true,
                        invoice: true,
                        delete: true,
                    }}
                    editing={editing}
                    title="Transactions"
                    searchPlaceholder="Search transactions…"
                    controls={<TransactionsFilters filters={filters} />}
                    toolbar={
                        <BalanceViewControl
                            wallets={wallets}
                            filters={filters}
                            active={balance}
                        />
                    }
                    action={
                        <Button
                            onClick={openAdd}
                            disabled={wallets.length === 0}
                            size="icon"
                            aria-label="Add transaction"
                            title="Add transaction"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <TransactionFormDialog
                key={`add-${addKey}`}
                open={addOpen}
                onOpenChange={setAddOpen}
                editing={null}
            />
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: '/transactions' }],
};

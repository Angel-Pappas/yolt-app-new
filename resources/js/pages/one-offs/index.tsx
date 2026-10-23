import { Head, useRemember } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DONE_ROW_CLASS } from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';
import { Button } from '@/components/ui/button';
import { TransactionFormDialog } from '@/pages/transactions/transaction-form-dialog';

/**
 * Finance ▸ One-offs: transactions tagged as one-off expenses, so they can be seen
 * apart from the day-to-day list. Same table, row actions and add form as
 * Transactions; a one-off is done once reconciled, and done ones only show (green)
 * with "Show all".
 */
export default function OneOffsIndex({
    transactions,
}: {
    transactions: Transaction[];
}) {
    const { wallets } = useFinanceLookups();
    const [showAll, setShowAll] = useRemember(false, 'one-offs-show-all');
    const [addOpen, setAddOpen] = useState(false);
    // Bumped on every open so the dialog remounts with a fresh form.
    const [addKey, setAddKey] = useState(0);

    const rows = showAll
        ? transactions
        : transactions.filter((t) => !t.is_reconciled);

    function openAdd() {
        setAddKey((k) => k + 1);
        setAddOpen(true);
    }

    return (
        <>
            <Head title="One-offs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TransactionsTable
                    transactions={rows}
                    rowActions={{
                        reconcile: true,
                        invoice: true,
                        delete: true,
                    }}
                    rowClassName={(t) =>
                        t.is_reconciled ? DONE_ROW_CLASS : undefined
                    }
                    title="One-offs"
                    searchPlaceholder="Search one-offs…"
                    emptyMessage={
                        showAll ? 'No one-offs yet.' : 'No open one-offs.'
                    }
                    controls={
                        <div className="flex">
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                    action={
                        <Button
                            onClick={openAdd}
                            disabled={wallets.length === 0}
                            size="icon"
                            aria-label="Add one-off"
                            title="Add one-off"
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
                oneOff
            />
        </>
    );
}

OneOffsIndex.layout = {
    breadcrumbs: [{ title: 'One-offs', href: '/one-offs' }],
};

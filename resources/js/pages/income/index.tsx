import { Head, useRemember } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DONE_ROW_CLASS } from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';
import { Button } from '@/components/ui/button';
import { type DuePreset, DUE_PRESETS, isDue } from '@/lib/income';
import { cn } from '@/lib/utils';
import { TransactionFormDialog } from '@/pages/transactions/transaction-form-dialog';

/**
 * Finance ▸ Income: every income with its status tag — what is due, and who has
 * paid. Same table, row actions and add form as Transactions. Only open incomes
 * (agreed or invoiced) show; "Show all" brings back the paid (green) and dropped
 * ones. The due buttons narrow the open ones by payment date.
 */
export default function IncomeIndex({
    transactions,
}: {
    transactions: Transaction[];
}) {
    const { wallets } = useFinanceLookups();
    const [showAll, setShowAll] = useRemember(false, 'income-show-all');
    const [due, setDue] = useRemember<DuePreset>('all', 'income-due');
    const [addOpen, setAddOpen] = useState(false);
    // Bumped on every open so the dialog remounts with a fresh form.
    const [addKey, setAddKey] = useState(0);

    const today = new Date().toISOString().slice(0, 10);
    const rows = transactions.filter(
        (t) =>
            (showAll ||
                t.income_status === 'agreed' ||
                t.income_status === 'invoiced') &&
            isDue(t.date.slice(0, 10), due, today),
    );

    function openAdd() {
        setAddKey((k) => k + 1);
        setAddOpen(true);
    }

    return (
        <>
            <Head title="Income" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TransactionsTable
                    transactions={rows}
                    showStatus
                    hideColumns={['type']}
                    rowActions={{
                        reconcile: true,
                        invoice: true,
                        delete: true,
                        exclude: true,
                    }}
                    rowClassName={(t) =>
                        t.income_status === 'paid' ? DONE_ROW_CLASS : undefined
                    }
                    title="Income"
                    searchPlaceholder="Search income…"
                    emptyMessage={
                        showAll ? 'No income here.' : 'No open income here.'
                    }
                    controls={
                        <div className="flex flex-wrap items-center gap-2">
                            {DUE_PRESETS.map((p) => (
                                <Button
                                    key={p.key}
                                    variant="outline"
                                    size="sm"
                                    aria-pressed={due === p.key}
                                    className={cn(
                                        due === p.key &&
                                            'text-primary border-primary',
                                    )}
                                    onClick={() => setDue(p.key)}
                                >
                                    {p.label}
                                </Button>
                            ))}
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                    action={
                        <Button
                            onClick={openAdd}
                            disabled={wallets.length === 0}
                            size="icon"
                            aria-label="Add income"
                            title="Add income"
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
                income
            />
        </>
    );
}

IncomeIndex.layout = {
    breadcrumbs: [{ title: 'Income', href: '/income' }],
};

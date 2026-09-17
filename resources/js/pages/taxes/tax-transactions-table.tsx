import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';

/** The transactions feeding a tax are the same canonical shape as everywhere else. */
export type TaxTransaction = Transaction;

/**
 * The "Contributing transactions" table on the Tax pages — the shared
 * {@see TransactionsTable}, so a row opens the same edit modal (and new-tab link) as
 * anywhere else. No per-row reconcile/invoice/delete here — it's a "what feeds this
 * tax" view — but editing and the ⓘ description work.
 */
export function TaxTransactionsTable({
    transactions,
}: {
    transactions: Transaction[];
}) {
    return (
        <TransactionsTable
            transactions={transactions}
            emptyMessage="No transactions contribute to this tax yet."
        />
    );
}

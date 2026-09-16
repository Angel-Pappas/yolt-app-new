import { Head } from '@inertiajs/react';
import {
    MonthlyLedgerTable,
    type MonthlyLedgerRow,
} from './monthly-ledger-table';
import {
    TaxTransactionsTable,
    type TaxTransaction,
} from './tax-transactions-table';

export default function TaxesFmy({
    rows,
    transactions,
}: {
    rows: MonthlyLedgerRow[];
    transactions: TaxTransaction[];
}) {
    return (
        <>
            <Head title="FMY (payroll tax)" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">FMY (payroll tax)</h1>
                <p className="text-muted-foreground text-sm">
                    Payroll income-tax withholding (ΦΜΥ) kept back each month,
                    by invoice date, is remitted to the state on the last
                    working day of the following month.
                </p>

                <MonthlyLedgerTable
                    rows={rows}
                    amountLabel="FMY this month"
                    emptyMessage="No FMY activity yet."
                />

                <h2 className="text-lg font-semibold">
                    Contributing transactions
                </h2>
                <TaxTransactionsTable transactions={transactions} />
            </div>
        </>
    );
}

TaxesFmy.layout = {
    breadcrumbs: [
        { title: 'Taxes', href: '/taxes' },
        { title: 'FMY', href: '/taxes/fmy' },
    ],
};

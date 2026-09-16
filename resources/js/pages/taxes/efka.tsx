import { Head } from '@inertiajs/react';
import {
    MonthlyLedgerTable,
    type MonthlyLedgerRow,
} from './monthly-ledger-table';

export default function TaxesEfka({ rows }: { rows: MonthlyLedgerRow[] }) {
    return (
        <>
            <Head title="EFKA (social security)" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">
                    EFKA (social security)
                </h1>
                <p className="text-muted-foreground text-sm">
                    Employee + employer EFKA collected each month, by invoice
                    date, is paid to the state as one payment on the last
                    working day of the following month.
                </p>

                <MonthlyLedgerTable
                    rows={rows}
                    amountLabel="EFKA this month"
                    emptyMessage="No EFKA activity yet."
                />
            </div>
        </>
    );
}

TaxesEfka.layout = {
    breadcrumbs: [
        { title: 'Taxes', href: '/taxes' },
        { title: 'EFKA', href: '/taxes/efka' },
    ],
};

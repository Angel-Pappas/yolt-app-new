import { Head } from '@inertiajs/react';
import {
    MonthlyLedgerTable,
    type MonthlyLedgerRow,
} from './monthly-ledger-table';

export default function TaxesWithheld({ rows }: { rows: MonthlyLedgerRow[] }) {
    return (
        <>
            <Head title="Withholding tax" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Withholding tax</h1>
                <p className="text-muted-foreground text-sm">
                    Withholding kept back on expenses each month, by invoice
                    date, is remitted to the state on the last working day of
                    the following month.
                </p>

                <MonthlyLedgerTable
                    rows={rows}
                    amountLabel="Withheld this month"
                    emptyMessage="No withholding activity yet."
                />
            </div>
        </>
    );
}

TaxesWithheld.layout = {
    breadcrumbs: [
        { title: 'Taxes', href: '/taxes' },
        { title: 'Withholding tax', href: '/taxes/withheld' },
    ],
};

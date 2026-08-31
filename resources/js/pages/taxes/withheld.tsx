import { Head } from '@inertiajs/react';
import { formatAmount, formatMonthYear } from '@/lib/format';

type Row = {
    month: string;
    withheld: number | string;
    payable_this_month: number | string;
};

type Props = {
    rows: Row[];
};

export default function TaxesWithheld({ rows }: Props) {
    return (
        <>
            <Head title="Withholding tax" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Withholding tax</h1>
                <p className="text-muted-foreground text-sm">
                    Withholding kept back on expenses each month, by payment
                    date, is remitted to the state the following month.
                </p>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Month</th>
                                <th className="p-3 text-right font-medium">
                                    Withheld this month
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Payable this month
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.month} className="border-t">
                                    <td className="p-3 whitespace-nowrap">
                                        {formatMonthYear(r.month)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(r.withheld)}
                                    </td>
                                    <td className="p-3 text-right font-medium tabular-nums">
                                        {formatAmount(r.payable_this_month)}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No withholding activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
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

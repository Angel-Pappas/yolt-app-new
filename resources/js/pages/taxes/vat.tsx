import { Head, Link } from '@inertiajs/react';
import { formatAmount, formatMonthYear } from '@/lib/format';

/** First and last day of a "yyyy-mm" period. */
function monthBounds(key: string): { first: string; last: string } {
    const [year, month] = key.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();
    return {
        first: `${key}-01`,
        last: `${key}-${String(lastDay).padStart(2, '0')}`,
    };
}

type Row = {
    month: string;
    income_vat: number | string;
    expense_vat: number | string;
    net: number | string;
    rollover_in: number | string;
    payable_this_month: number | string;
    payable_next_month: number | string;
};

type Props = {
    rows: Row[];
};

export default function TaxesVat({ rows }: Props) {
    return (
        <>
            <Head title="VAT" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">VAT</h1>
                <p className="text-muted-foreground text-sm">
                    Output VAT (income) less input VAT (expenses) per month, by
                    invoice date. A credit rolls forward; a debit over €100 is
                    split into two equal installments.
                </p>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Month</th>
                                <th className="p-3 text-right font-medium">
                                    Income VAT
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Expenses VAT
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Net VAT
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Roll over
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Payable this month
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Payable next month
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.month} className="border-t">
                                    <td className="p-3 whitespace-nowrap">
                                        <Link
                                            href={`/transactions?invoice_from=${monthBounds(r.month).first}&invoice_to=${monthBounds(r.month).last}&all=1`}
                                            className="hover:underline"
                                        >
                                            {formatMonthYear(r.month)}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(r.income_vat)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(r.expense_vat)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(r.net)}
                                    </td>
                                    <td className="text-muted-foreground p-3 text-right tabular-nums">
                                        {formatAmount(r.rollover_in)}
                                    </td>
                                    <td className="p-3 text-right font-medium tabular-nums">
                                        {formatAmount(r.payable_this_month)}
                                    </td>
                                    <td className="text-muted-foreground p-3 text-right tabular-nums">
                                        {formatAmount(r.payable_next_month)}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No VAT activity yet.
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

TaxesVat.layout = {
    breadcrumbs: [
        { title: 'Taxes', href: '/taxes' },
        { title: 'VAT', href: '/taxes/vat' },
    ],
};

import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { formatAmount, formatMonthYear } from '@/lib/format';

type Row = {
    month: string;
    withheld: number | string;
    payable_this_month: number | string;
};

type Props = { rows: Row[] };

/** First and last day of a "yyyy-mm" period. */
function monthBounds(key: string): { first: string; last: string } {
    const [year, month] = key.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();
    return {
        first: `${key}-01`,
        last: `${key}-${String(lastDay).padStart(2, '0')}`,
    };
}

export default function TaxesWithheld({ rows }: Props) {
    const columns: ColumnDef<Row>[] = [
        {
            accessorKey: 'month',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Month" />
            ),
            cell: ({ row }) => {
                const { first, last } = monthBounds(row.original.month);
                return (
                    <Link
                        href={`/transactions?from=${first}&to=${last}&type=expense&all=1`}
                        className="whitespace-nowrap hover:underline"
                    >
                        {formatMonthYear(row.original.month)}
                    </Link>
                );
            },
        },
        {
            id: 'withheld',
            accessorFn: (row) => Number(row.withheld),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title="Withheld this month"
                    align="right"
                />
            ),
            cell: ({ row }) => formatAmount(row.original.withheld),
        },
        {
            id: 'payable_this_month',
            accessorFn: (row) => Number(row.payable_this_month),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title="Payable this month"
                    align="right"
                />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(row.original.payable_this_month)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Withholding tax" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Withholding tax</h1>
                <p className="text-muted-foreground text-sm">
                    Withholding kept back on expenses each month, by payment
                    date, is remitted to the state the following month.
                </p>

                <DataTable
                    columns={columns}
                    data={rows}
                    emptyMessage="No withholding activity yet."
                    pageSize={1000}
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

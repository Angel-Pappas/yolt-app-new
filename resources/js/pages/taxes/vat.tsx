import { Head, Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';

type Row = {
    month: string;
    income_vat: number | string;
    expense_vat: number | string;
    net: number | string;
    rollover_in: number | string;
    payable: number | string;
    due_date: string;
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

function amountColumn(
    key: keyof Row,
    title: string,
    muted = false,
): ColumnDef<Row> {
    return {
        id: key,
        accessorFn: (row) => Number(row[key]),
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title={title} align="right" />
        ),
        cell: ({ row }) => (
            <span className={muted ? 'text-muted-foreground' : undefined}>
                {formatAmount(row.original[key])}
            </span>
        ),
    };
}

export default function TaxesVat({ rows }: Props) {
    const columns: ColumnDef<Row>[] = [
        {
            id: 'month',
            // Mid-month ISO so the date-range header filter compares correctly
            // (a bare "yyyy-mm" would mis-sort against "yyyy-mm-dd" bounds).
            accessorFn: (row) => `${row.month}-15`,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Month" />
            ),
            cell: ({ row }) => {
                const { first, last } = monthBounds(row.original.month);
                return (
                    <Link
                        href={`/transactions?invoice_from=${first}&invoice_to=${last}&all=1`}
                        className="whitespace-nowrap hover:underline"
                    >
                        {formatMonthYear(row.original.month)}
                    </Link>
                );
            },
        },
        amountColumn('income_vat', 'Income VAT'),
        amountColumn('expense_vat', 'Expenses VAT'),
        amountColumn('net', 'Net VAT'),
        amountColumn('rollover_in', 'Roll over', true),
        amountColumn('payable', 'Payable'),
        {
            id: 'due_date',
            accessorFn: (row) => row.due_date,
            meta: { align: 'right', filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Due date" align="right" />
            ),
            cell: ({ row }) => (
                <span className="whitespace-nowrap tabular-nums">
                    {formatDate(row.original.due_date)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="VAT" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">VAT</h1>
                <p className="text-muted-foreground text-sm">
                    Output VAT (income) less input VAT (expenses) per month, by
                    invoice date. A credit rolls forward; a debit is paid in
                    full on the last working day of the following month.
                </p>

                <DataTable
                    columns={columns}
                    data={rows}
                    emptyMessage="No VAT activity yet."
                    pageSize={1000}
                />
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

import { Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';

export type MonthlyLedgerRow = {
    month: string;
    amount: number | string;
    due_date: string;
};

/** First and last day of a "yyyy-mm" period. */
function monthBounds(key: string): { first: string; last: string } {
    const [year, month] = key.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();
    return {
        first: `${key}-01`,
        last: `${key}-${String(lastDay).padStart(2, '0')}`,
    };
}

/**
 * The shared table for a simple monthly tax (withholding, FMY, EFKA): Month | the
 * collected amount | Due date. Each month links to its contributing transactions by
 * invoice date. VAT and income tax have their own (richer) tables.
 */
export function MonthlyLedgerTable({
    rows,
    amountLabel,
    emptyMessage,
}: {
    rows: MonthlyLedgerRow[];
    amountLabel: string;
    emptyMessage: string;
}) {
    const columns: ColumnDef<MonthlyLedgerRow>[] = [
        {
            id: 'month',
            // Mid-month ISO so the date-range header filter compares correctly.
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
        {
            id: 'amount',
            accessorFn: (row) => Number(row.amount),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title={amountLabel}
                    align="right"
                />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(row.original.amount)}
                </span>
            ),
        },
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
        <DataTable
            columns={columns}
            data={rows}
            emptyMessage={emptyMessage}
            pageSize={1000}
        />
    );
}

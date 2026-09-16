import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Related = { id: number; name: string } | null;
type TransactionType = 'income' | 'expense' | 'transfer';

export type TaxTransaction = {
    id: number;
    date: string;
    type: TransactionType;
    description: string;
    net: string;
    vat_amount: string;
    withheld_amount: string;
    fmy_amount: string | null;
    efka_employee_amount: string | null;
    wallet: Related;
    entity: Related;
};

const typeMeta: Record<TransactionType, { label: string; className: string }> =
    {
        income: {
            label: 'Income',
            className: 'text-green-600 dark:text-green-500',
        },
        expense: {
            label: 'Expense',
            className: 'text-red-600 dark:text-red-500',
        },
        transfer: { label: 'Transfer', className: 'text-muted-foreground' },
    };

function total(t: TaxTransaction): number {
    return (
        Number(t.net) +
        Number(t.vat_amount) -
        Number(t.withheld_amount) -
        Number(t.fmy_amount ?? 0) -
        Number(t.efka_employee_amount ?? 0)
    );
}

/**
 * A read-only table of the transactions contributing to a tax — the same columns
 * as the Transactions list (Type · Date · Wallet · Entity · Description · Net · VAT ·
 * Total), with no row actions since this view is about "what makes up this tax".
 */
export function TaxTransactionsTable({
    transactions,
}: {
    transactions: TaxTransaction[];
}) {
    const columns: ColumnDef<TaxTransaction>[] = [
        {
            accessorKey: 'type',
            meta: {
                filter: {
                    type: 'select',
                    options: [
                        { value: 'income', label: 'Income' },
                        { value: 'expense', label: 'Expense' },
                    ],
                },
            },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'font-medium',
                        typeMeta[row.original.type].className,
                    )}
                >
                    {typeMeta[row.original.type].label}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap tabular-nums">
                    {formatDate(row.original.date)}
                </span>
            ),
        },
        {
            id: 'wallet',
            accessorFn: (row) => row.wallet?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Wallet" />
            ),
            cell: ({ row }) => row.original.wallet?.name ?? '—',
        },
        {
            id: 'entity',
            accessorFn: (row) => row.entity?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.entity?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'description',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'net',
            accessorFn: (row) => Number(row.net),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
        },
        {
            id: 'vat',
            accessorFn: (row) => Number(row.vat_amount),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="VAT" align="right" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatAmount(row.original.vat_amount)}
                </span>
            ),
        },
        {
            id: 'total',
            accessorFn: (row) => total(row),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Total" align="right" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(total(row.original))}
                </span>
            ),
        },
    ];

    return (
        <DataTable
            columns={columns}
            data={transactions}
            searchPlaceholder="Search transactions…"
            emptyMessage="No transactions contribute to this tax yet."
            pageSize={50}
        />
    );
}

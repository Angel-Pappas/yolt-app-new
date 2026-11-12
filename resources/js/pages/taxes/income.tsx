import { Head } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { CrudResource } from '@/components/crud/crud-resource';
import { DataTable } from '@/components/data-table/data-table';
import { Badge } from '@/components/ui/badge';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';

type IncomeTaxYear = {
    id: number;
    year: number;
    revenue_before_tax: string;
    total_tax: string;
    advance_amount: string | null;
    first_installment_month: string;
    last_installment_month: string;
    monthly_installment_amount: string;
};

type ScheduleRow = {
    year: number;
    month: string;
    amount: number | string;
    due_date: string;
    /** From the estimate (no entered figure for that year yet). */
    estimated: boolean;
};

/** One fiscal year's estimate (`App\Support\IncomeTaxEstimate`). */
type Estimate = {
    year: number;
    income: number;
    expenses: number;
    profit: number;
    tax: number;
    advance: number;
    credit: number;
    payable: number;
};

type Props = {
    years: IncomeTaxYear[];
    schedule: ScheduleRow[];
    estimates: Estimate[];
};

const estimateColumns: ColumnDef<Estimate>[] = [
    {
        accessorKey: 'year',
        header: ({ column }) => (
            <ColumnHeader column={column} title="Fiscal year" />
        ),
        cell: ({ row }) => (
            <span className="font-medium">{row.original.year}</span>
        ),
    },
    ...(
        [
            ['income', 'Income'],
            ['expenses', 'Expenses'],
            ['profit', 'Profit'],
            ['tax', 'Tax + levy'],
            ['advance', 'Advance'],
            ['credit', 'Already advanced'],
            ['payable', 'To pay'],
        ] as [keyof Estimate, string][]
    ).map(([key, title]): ColumnDef<Estimate> => ({
        id: key,
        accessorFn: (row) => row[key],
        meta: { align: 'right' },
        header: ({ column }) => (
            <ColumnHeader column={column} title={title} align="right" />
        ),
        cell: ({ row }) => (
            <span
                className={
                    key === 'payable' ? 'font-medium' : 'text-muted-foreground'
                }
            >
                {key === 'credit' && row.original.credit > 0 ? '−' : ''}
                {formatAmount(row.original[key])}
            </span>
        ),
    })),
];

/** "YYYY-MM..." → e.g. "June 2026". */
function monthLabel(value: string): string {
    return formatMonthYear(String(value).slice(0, 7));
}

export default function TaxesIncome({ years, schedule, estimates }: Props) {
    const scheduleColumns: ColumnDef<ScheduleRow>[] = [
        {
            id: 'month',
            accessorFn: (row) => `${row.month}-15`,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Month" />
            ),
            cell: ({ row }) => (
                <span className="flex items-center gap-2 whitespace-nowrap">
                    {monthLabel(row.original.month)}
                    {row.original.estimated && (
                        <Badge variant="secondary">Estimate</Badge>
                    )}
                </span>
            ),
        },
        {
            id: 'amount',
            accessorFn: (row) => Number(row.amount),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title="Installment"
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
        <>
            <Head title="Income tax" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Income tax</h1>
                    <p className="text-muted-foreground text-sm">
                        Estimated from the transactions until the accountant's
                        figure for a year is entered below. Each installment is
                        paid on that month's last working day.
                    </p>
                </div>

                {estimates.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h2 className="text-lg font-semibold">Estimates</h2>
                        <DataTable
                            columns={estimateColumns}
                            data={estimates}
                            pageSize={1000}
                        />
                    </div>
                )}

                <CrudResource
                    title="Years"
                    singular="income tax year"
                    baseUrl="/taxes/income"
                    items={years}
                    columns={[
                        {
                            key: 'year',
                            label: 'Year',
                            filter: { type: 'number' },
                        },
                        {
                            key: 'revenue_before_tax',
                            label: 'Revenue before tax',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                formatAmount(String(item.revenue_before_tax)),
                        },
                        {
                            key: 'total_tax',
                            label: 'Total tax',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                formatAmount(String(item.total_tax)),
                        },
                        {
                            key: 'first_installment_month',
                            label: 'First installment',
                            render: (item) =>
                                monthLabel(
                                    String(item.first_installment_month),
                                ),
                        },
                        {
                            key: 'last_installment_month',
                            label: 'Last installment',
                            render: (item) =>
                                monthLabel(String(item.last_installment_month)),
                        },
                        {
                            key: 'monthly_installment_amount',
                            label: 'Monthly installment',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                formatAmount(
                                    String(item.monthly_installment_amount),
                                ),
                        },
                    ]}
                    fields={[
                        {
                            key: 'year',
                            label: 'Year',
                            type: 'text',
                            required: true,
                        },
                        {
                            key: 'revenue_before_tax',
                            label: 'Revenue before tax (€)',
                            type: 'money',
                            required: true,
                        },
                        {
                            key: 'total_tax',
                            label: 'Total tax (€)',
                            type: 'money',
                            required: true,
                        },
                        {
                            key: 'advance_amount',
                            label: 'Of which advance toward the next year (€, optional)',
                            type: 'money',
                        },
                        {
                            key: 'first_installment_month',
                            label: 'First installment month',
                            type: 'date',
                            required: true,
                        },
                        {
                            key: 'last_installment_month',
                            label: 'Last installment month',
                            type: 'date',
                            required: true,
                        },
                        {
                            key: 'monthly_installment_amount',
                            label: 'Monthly installment (€)',
                            type: 'money',
                            required: true,
                        },
                    ]}
                    description="The accountant's figure for a year and the months its installments are paid. It replaces the estimate for the installments starting that year."
                />

                <div className="flex flex-col gap-2">
                    <h2 className="text-lg font-semibold">
                        Installment schedule
                    </h2>
                    <DataTable
                        columns={scheduleColumns}
                        data={schedule}
                        emptyMessage="No installments scheduled yet."
                        pageSize={1000}
                    />
                </div>
            </div>
        </>
    );
}

TaxesIncome.layout = {
    breadcrumbs: [
        { title: 'Taxes', href: '/taxes' },
        { title: 'Income tax', href: '/taxes/income' },
    ],
};

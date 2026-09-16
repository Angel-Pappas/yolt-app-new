import { Head } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { CrudResource } from '@/components/crud/crud-resource';
import { DataTable } from '@/components/data-table/data-table';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';

type IncomeTaxYear = {
    id: number;
    year: number;
    revenue_before_tax: string;
    total_tax: string;
    first_installment_month: string;
    last_installment_month: string;
    monthly_installment_amount: string;
};

type ScheduleRow = {
    year: number;
    month: string;
    amount: number | string;
    due_date: string;
};

type Props = {
    years: IncomeTaxYear[];
    schedule: ScheduleRow[];
};

/** "YYYY-MM..." → e.g. "June 2026". */
function monthLabel(value: string): string {
    return formatMonthYear(String(value).slice(0, 7));
}

export default function TaxesIncome({ years, schedule }: Props) {
    const scheduleColumns: ColumnDef<ScheduleRow>[] = [
        {
            id: 'month',
            accessorFn: (row) => `${row.month}-15`,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Month" />
            ),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {monthLabel(row.original.month)}
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
                        Enter each fiscal year's tax and its installment window.
                        Each installment is paid on that month's last working
                        day.
                    </p>
                </div>

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
                            type: 'decimal',
                            required: true,
                        },
                        {
                            key: 'total_tax',
                            label: 'Total tax (€)',
                            type: 'decimal',
                            required: true,
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
                            type: 'decimal',
                            required: true,
                        },
                    ]}
                    description="A fiscal year's income tax and the months its installments are paid."
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

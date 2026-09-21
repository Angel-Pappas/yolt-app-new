import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';

type Amount = number | string;

type Obligation = {
    tax: string;
    period: string;
    amount: Amount;
    due_date: string;
};

type Wallet = { id: number; name: string };

type Props = {
    obligations: Obligation[];
    current_month: string;
    wallets: Wallet[];
    tax_wallet_id: number | null;
    vat: { payable_this_month: Amount; net: Amount };
    withheld: { payable_this_month: Amount; this_month: Amount };
    fmy: { payable_this_month: Amount; this_month: Amount };
    efka: { payable_this_month: Amount; this_month: Amount };
    income: { payable_this_month: Amount; this_year: Amount };
};

/** Label + per-tax page for each tax key. */
const TAX_META: Record<string, { label: string; href: string }> = {
    vat: { label: 'VAT', href: '/taxes/vat' },
    withheld: { label: 'Withholding tax', href: '/taxes/withheld' },
    fmy: { label: 'FMY', href: '/taxes/fmy' },
    efka: { label: 'EFKA', href: '/taxes/efka' },
    income: { label: 'Income tax', href: '/taxes/income' },
};

/** Shift a "YYYY-MM" month by a number of months. */
function shiftMonth(month: string, delta: number): string {
    const [year, m] = month.split('-').map(Number);
    const d = new Date(year, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function Figure({ label, value }: { label: string; value: Amount }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="text-lg font-semibold tabular-nums">
                {formatAmount(value)}
            </div>
        </div>
    );
}

function TaxCard({
    href,
    title,
    primary,
    secondary,
}: {
    href: string;
    title: string;
    primary: { label: string; value: Amount };
    secondary: { label: string; value: Amount };
}) {
    return (
        <Link href={href} className="block">
            <Card className="hover:border-primary/50 transition-colors">
                <CardHeader className="flex-row items-center justify-between space-y-0">
                    <CardTitle>{title}</CardTitle>
                    <ChevronRight className="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent className="flex gap-8">
                    <Figure label={primary.label} value={primary.value} />
                    <Figure label={secondary.label} value={secondary.value} />
                </CardContent>
            </Card>
        </Link>
    );
}

export default function TaxesIndex({
    obligations,
    current_month,
    wallets,
    tax_wallet_id,
    vat,
    withheld,
    fmy,
    efka,
    income,
}: Props) {
    const [month, setMonth] = useState(current_month);

    function setTaxWallet(value: string) {
        router.patch(
            '/taxes/wallet',
            { tax_wallet_id: Number(value) },
            { preserveScroll: true },
        );
    }

    const due = obligations.filter((o) => String(o.due_date).startsWith(month));
    const total = due.reduce((sum, o) => sum + Number(o.amount), 0);

    const columns: ColumnDef<Obligation>[] = [
        {
            id: 'tax',
            accessorFn: (row) => TAX_META[row.tax]?.label ?? row.tax,
            header: ({ column }) => (
                <ColumnHeader column={column} title="Tax" />
            ),
            cell: ({ row }) => {
                const meta = TAX_META[row.original.tax];
                return meta ? (
                    <Link
                        href={meta.href}
                        className="font-medium hover:underline"
                    >
                        {meta.label}
                    </Link>
                ) : (
                    row.original.tax
                );
            },
        },
        {
            id: 'period',
            accessorFn: (row) => row.period,
            header: ({ column }) => (
                <ColumnHeader column={column} title="For" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {formatMonthYear(row.original.period)}
                </span>
            ),
        },
        {
            id: 'amount',
            accessorFn: (row) => Number(row.amount),
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Amount" align="right" />
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
            meta: { align: 'right' },
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
            <Head title="Taxes" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Taxes</h1>

                <Card>
                    <CardHeader>
                        <CardTitle>Tax payments</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3">
                        <Label htmlFor="tax_wallet">Default wallet</Label>
                        <Select
                            value={tax_wallet_id ? String(tax_wallet_id) : ''}
                            onValueChange={setTaxWallet}
                        >
                            <SelectTrigger id="tax_wallet" className="w-56">
                                <SelectValue placeholder="Select a wallet" />
                            </SelectTrigger>
                            <SelectContent>
                                {wallets.map((w) => (
                                    <SelectItem key={w.id} value={String(w.id)}>
                                        {w.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <span className="text-muted-foreground text-sm">
                            Generated tax transactions are filed here. Changing
                            it moves every unreconciled tax transaction to the
                            new wallet.
                        </span>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-2">
                    <DataTable
                        columns={columns}
                        data={due}
                        pageSize={1000}
                        emptyMessage="Nothing due this month."
                        controls={
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            setMonth(shiftMonth(month, -1))
                                        }
                                        aria-label="Previous month"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <span className="w-36 text-center font-medium">
                                        {formatMonthYear(month)}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            setMonth(shiftMonth(month, 1))
                                        }
                                        aria-label="Next month"
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                                <div className="ml-auto text-sm">
                                    <span className="text-muted-foreground">
                                        Total due:{' '}
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {formatAmount(total)}
                                    </span>
                                </div>
                            </div>
                        }
                    />
                </div>

                <div className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">Tax types</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <TaxCard
                            href="/taxes/vat"
                            title="VAT"
                            primary={{
                                label: 'Payable this month',
                                value: vat.payable_this_month,
                            }}
                            secondary={{
                                label: "This month's net",
                                value: vat.net,
                            }}
                        />
                        <TaxCard
                            href="/taxes/withheld"
                            title="Withholding tax"
                            primary={{
                                label: 'Payable this month',
                                value: withheld.payable_this_month,
                            }}
                            secondary={{
                                label: 'Withheld this month',
                                value: withheld.this_month,
                            }}
                        />
                        <TaxCard
                            href="/taxes/fmy"
                            title="FMY (payroll tax)"
                            primary={{
                                label: 'Payable this month',
                                value: fmy.payable_this_month,
                            }}
                            secondary={{
                                label: 'FMY this month',
                                value: fmy.this_month,
                            }}
                        />
                        <TaxCard
                            href="/taxes/efka"
                            title="EFKA (social security)"
                            primary={{
                                label: 'Payable this month',
                                value: efka.payable_this_month,
                            }}
                            secondary={{
                                label: 'EFKA this month',
                                value: efka.this_month,
                            }}
                        />
                        <TaxCard
                            href="/taxes/income"
                            title="Income tax"
                            primary={{
                                label: 'Payable this month',
                                value: income.payable_this_month,
                            }}
                            secondary={{
                                label: 'Payable this year',
                                value: income.this_year,
                            }}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

TaxesIndex.layout = {
    breadcrumbs: [{ title: 'Taxes', href: '/taxes' }],
};

import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ChevronDown } from 'lucide-react';
import { CashFlowChart } from '@/components/cash-flow-chart';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatAmount, formatMonthYear } from '@/lib/format';
import { monthKey } from '@/lib/income';
import { cn } from '@/lib/utils';

/** One month of `App\Support\Projection`. */
type Row = {
    month: string;
    starting: number;
    income: number;
    payroll: number;
    contractors: number;
    taxes: number;
    other: number;
    expenses: number;
    transfers: number;
    net: number;
    ending: number;
};

type Settings = {
    back: number;
    ahead: number;
    wallets: number[] | null;
};

type Props = {
    rows: Row[];
    settings: Settings;
    wallets: { id: number; name: string }[];
    current_month: string;
};

/** The last day of a 'YYYY-MM' month, ISO. */
function monthEnd(month: string): string {
    const [y, m] = month.split('-').map(Number);
    return `${month}-${String(new Date(Date.UTC(y, m, 0)).getUTCDate()).padStart(2, '0')}`;
}

/** A column of amounts; `tone` colours it, `strong` marks a headline figure. */
function money(
    key: keyof Omit<Row, 'month'>,
    title: string,
    opts: { strong?: boolean; signed?: boolean } = {},
): ColumnDef<Row> {
    return {
        id: key,
        accessorFn: (row) => row[key],
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title={title} align="right" />
        ),
        cell: ({ row }) => {
            const value = row.original[key];
            return (
                <span
                    className={cn(
                        opts.strong ? 'font-medium' : 'text-muted-foreground',
                        (opts.signed || key === 'ending') &&
                            value < 0 &&
                            'text-rose-600 dark:text-rose-500',
                    )}
                >
                    {formatAmount(value)}
                </span>
            );
        },
    };
}

/**
 * Finance ▸ Projection: income, expenses and balance month by month — the past
 * from what happened, the future from what is planned (recurring costs, project
 * installments, one-offs and the taxes they cause). The graph and the table show
 * the same months. The range, wallets and the unreconciled option are remembered
 * for the next visit. A month opens its transactions.
 */
export default function ProjectionIndex({
    rows,
    settings,
    wallets,
    current_month,
}: Props) {
    function apply(patch: Partial<Settings>) {
        const next = { ...settings, ...patch };
        router.get(
            '/projection',
            {
                back: next.back,
                ahead: next.ahead,
                // An empty list means every wallet.
                wallets: next.wallets ?? [],
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    const fromMonth = monthKey(`${current_month}-01`, -settings.back);
    const toMonth = monthKey(`${current_month}-01`, settings.ahead);
    // Months offered by the range pickers: two years either side of now.
    const backOptions = Array.from({ length: 25 }, (_, i) => i);
    const aheadOptions = Array.from({ length: 37 }, (_, i) => i);

    const allWallets = settings.wallets === null;
    const chosen = new Set(settings.wallets ?? wallets.map((w) => w.id));
    function toggleWallet(id: number) {
        const next = new Set(chosen);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        // Everything ticked (or nothing) is simply "all wallets".
        apply({
            wallets:
                next.size === 0 || next.size === wallets.length
                    ? null
                    : [...next],
        });
    }

    const showTransfers = rows.some((r) => r.transfers !== 0);
    const lowest = rows
        .filter((r) => r.month >= current_month)
        .reduce<Row | null>(
            (low, r) => (low === null || r.ending < low.ending ? r : low),
            null,
        );

    const columns: ColumnDef<Row>[] = [
        {
            id: 'month',
            accessorFn: (row) => row.month,
            header: ({ column }) => (
                <ColumnHeader column={column} title="Month" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'whitespace-nowrap',
                        row.original.month === current_month && 'font-semibold',
                    )}
                >
                    {formatMonthYear(row.original.month)}
                </span>
            ),
        },
        money('starting', 'Starting'),
        money('income', 'Income', { strong: true }),
        money('other', 'Expenses'),
        money('payroll', 'Payroll'),
        money('contractors', 'Contractors'),
        money('taxes', 'Taxes'),
        money('expenses', 'Total out', { strong: true }),
        ...(showTransfers
            ? [money('transfers', 'Transfers', { signed: true })]
            : []),
        money('net', 'Net', { signed: true }),
        money('ending', 'Ending', { strong: true }),
    ];

    return (
        <>
            <Head title="Projection" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Projection</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={String(settings.back)}
                        onValueChange={(v) => apply({ back: Number(v) })}
                    >
                        <SelectTrigger className="w-44" aria-label="From month">
                            <SelectValue>
                                From {formatMonthYear(fromMonth)}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {backOptions.map((n) => (
                                <SelectItem key={n} value={String(n)}>
                                    {formatMonthYear(
                                        monthKey(`${current_month}-01`, -n),
                                    )}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={String(settings.ahead)}
                        onValueChange={(v) => apply({ ahead: Number(v) })}
                    >
                        <SelectTrigger className="w-44" aria-label="To month">
                            <SelectValue>
                                To {formatMonthYear(toMonth)}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {aheadOptions.map((n) => (
                                <SelectItem key={n} value={String(n)}>
                                    {formatMonthYear(
                                        monthKey(`${current_month}-01`, n),
                                    )}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Popover>
                        <PopoverTrigger asChild>
                            <Button variant="outline" size="sm">
                                {allWallets
                                    ? 'All wallets'
                                    : `${chosen.size} of ${wallets.length} wallets`}
                                <ChevronDown className="size-4 opacity-60" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="start"
                            className="grid w-56 gap-2"
                        >
                            {wallets.map((w) => (
                                <label
                                    key={w.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={chosen.has(w.id)}
                                        onCheckedChange={() =>
                                            toggleWallet(w.id)
                                        }
                                    />
                                    {w.name}
                                </label>
                            ))}
                        </PopoverContent>
                    </Popover>
                </div>
                <Card>
                    <CardContent className="grid min-w-0 gap-4">
                        {lowest && (
                            <div className="text-sm">
                                <span className="text-muted-foreground">
                                    Lowest balance ahead:{' '}
                                </span>
                                <span
                                    className={cn(
                                        'font-medium tabular-nums',
                                        lowest.ending < 0 &&
                                            'text-rose-600 dark:text-rose-500',
                                    )}
                                >
                                    {formatAmount(lowest.ending)}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    at the end of{' '}
                                    {formatMonthYear(lowest.month)}
                                </span>
                            </div>
                        )}
                        <CashFlowChart
                            points={rows}
                            currentMonth={current_month}
                        />
                    </CardContent>
                </Card>

                <DataTable
                    columns={columns}
                    data={rows}
                    pageSize={1000}
                    emptyMessage="Nothing to project yet."
                    onRowClick={(r) =>
                        router.visit(
                            `/transactions?from=${r.month}-01&to=${monthEnd(r.month)}`,
                        )
                    }
                    rowHref={(r) =>
                        `/transactions?from=${r.month}-01&to=${monthEnd(r.month)}`
                    }
                    rowClassName={(r) =>
                        r.month === current_month ? 'bg-muted/40' : undefined
                    }
                />
            </div>
        </>
    );
}

ProjectionIndex.layout = {
    breadcrumbs: [{ title: 'Projection', href: '/projection' }],
};

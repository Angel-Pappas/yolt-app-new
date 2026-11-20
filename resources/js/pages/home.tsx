import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { CashFlowChart } from '@/components/cash-flow-chart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatAmount, formatDate, formatMonthYear } from '@/lib/format';
import { cn } from '@/lib/utils';

type Tally = { count: number; total: number };

type Props = {
    wallets: { id: number; name: string; balance: number }[];
    income: { overdue: Tally; this_month: Tally };
    taxes_this_month: number;
    not_matched: number;
    agreements_ending: {
        id: number;
        title: string;
        entity: { id: number; name: string };
        end_date: string;
        days_left: number;
    }[];
    projection: {
        month: string;
        income: number;
        expenses: number;
        ending: number;
    }[];
    current_month: string;
};

/** One headline figure; the whole tile is a link to the page behind it. */
function Tile({
    href,
    label,
    value,
    hint,
    tone,
}: {
    href: string;
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'warn' | 'bad';
}) {
    return (
        <Link
            href={href}
            className="hover:bg-muted/50 rounded-xl border p-4 transition-colors"
        >
            <div className="text-muted-foreground text-sm">{label}</div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tabular-nums',
                    tone === 'warn' && 'text-amber-600',
                    tone === 'bad' && 'text-rose-600 dark:text-rose-500',
                )}
            >
                {value}
            </div>
            {hint && (
                <div className="text-muted-foreground mt-1 text-sm">{hint}</div>
            )}
        </Link>
    );
}

function count(n: number, noun: string): string {
    return `${n} ${noun}${n === 1 ? '' : 's'}`;
}

/**
 * Home: what needs attention today. Every figure links to the page behind it.
 */
export default function Home({
    wallets,
    income,
    taxes_this_month,
    not_matched,
    agreements_ending,
    projection,
    current_month,
}: Props) {
    const total = wallets.reduce((s, w) => s + w.balance, 0);
    const lowest = projection.reduce<(typeof projection)[number] | null>(
        (low, r) => (low === null || r.ending < low.ending ? r : low),
        null,
    );

    return (
        <>
            <Head title="Home" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Tile
                        href="/wallets"
                        label="In the wallets"
                        value={formatAmount(total)}
                        tone={total < 0 ? 'bad' : undefined}
                        hint={wallets
                            .filter((w) => w.balance !== 0)
                            .map((w) => `${w.name} ${formatAmount(w.balance)}`)
                            .join(' · ')}
                    />
                    <Tile
                        href="/planning/income"
                        label="Income due this month"
                        value={formatAmount(income.this_month.total)}
                        hint={
                            <>
                                {count(income.this_month.count, 'payment')}
                                {income.overdue.count > 0 && (
                                    <span className="text-rose-600 dark:text-rose-500">
                                        {' · '}
                                        {formatAmount(
                                            income.overdue.total,
                                        )}{' '}
                                        overdue
                                    </span>
                                )}
                            </>
                        }
                    />
                    <Tile
                        href="/planning/taxes"
                        label="Taxes to pay this month"
                        value={formatAmount(taxes_this_month)}
                    />
                    <Tile
                        href="/bank-import?tab=not-matched"
                        label="Not matched yet"
                        value={not_matched}
                        tone={not_matched > 0 ? 'warn' : undefined}
                        hint="Planned items nothing paid in a concluded period"
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <CardTitle>
                            <Link
                                href="/projection"
                                className="hover:underline"
                            >
                                The year ahead
                            </Link>
                        </CardTitle>
                        {lowest && (
                            <div className="text-sm">
                                <span className="text-muted-foreground">
                                    Lowest balance:{' '}
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
                    </CardHeader>
                    <CardContent>
                        <CashFlowChart
                            points={projection}
                            currentMonth={current_month}
                            height={260}
                        />
                    </CardContent>
                </Card>

                {agreements_ending.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <Link
                                    href="/agreements"
                                    className="hover:underline"
                                >
                                    Agreements ending soon
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            {agreements_ending.map((a) => (
                                <Link
                                    key={a.id}
                                    href={`/entities/${a.entity.id}`}
                                    className="hover:bg-muted/50 -mx-2 flex flex-wrap items-baseline justify-between gap-x-4 rounded-md px-2 py-1"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {a.title}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {a.entity.name}
                                        </span>
                                    </span>
                                    <span className="text-amber-600 tabular-nums">
                                        {formatDate(a.end_date)} ·{' '}
                                        {a.days_left === 0
                                            ? 'today'
                                            : `in ${count(a.days_left, 'day')}`}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

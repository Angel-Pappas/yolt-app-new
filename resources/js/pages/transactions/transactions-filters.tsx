import { router } from '@inertiajs/react';
import { endOfMonth, format, startOfMonth, subMonths } from 'date-fns';
import { CircleCheck, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import { cn } from '@/lib/utils';

export type TransactionFilters = {
    from: string | null;
    to: string | null;
    // Invoice-date range — set only by the Taxes VAT drill-down, preserved here.
    invoice_from: string | null;
    invoice_to: string | null;
    unreconciled: boolean;
    no_invoice: boolean;
    all: boolean;
};

type Props = {
    filters: TransactionFilters;
};

const iso = (date: Date) => format(date, 'yyyy-MM-dd');

export function TransactionsFilters({ filters }: Props) {
    const now = new Date();
    const thisFrom = iso(startOfMonth(now));
    const thisTo = iso(endOfMonth(now));
    const lastFrom = iso(startOfMonth(subMonths(now, 1)));
    const lastTo = iso(endOfMonth(subMonths(now, 1)));

    const isAllTime = filters.all;
    const isThisMonth =
        !filters.all && filters.from === thisFrom && filters.to === thisTo;
    const isLastMonth =
        !filters.all && filters.from === lastFrom && filters.to === lastTo;

    function apply(next: Partial<TransactionFilters>) {
        const merged = { ...filters, ...next };
        // Picking a date range leaves all-time; entering all-time drops the dates.
        if (next.from !== undefined || next.to !== undefined)
            merged.all = false;
        if (next.all) {
            merged.from = null;
            merged.to = null;
        }
        const params: Record<string, string> = {};
        if (merged.from) params.from = merged.from;
        if (merged.to) params.to = merged.to;
        if (merged.invoice_from) params.invoice_from = merged.invoice_from;
        if (merged.invoice_to) params.invoice_to = merged.invoice_to;
        if (merged.unreconciled) params.unreconciled = '1';
        if (merged.no_invoice) params.no_invoice = '1';
        if (merged.all) params.all = '1';

        router.get('/transactions', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="w-40">
                <DateField
                    value={filters.from ?? ''}
                    onChange={(iso) => apply({ from: iso || null })}
                />
            </div>
            <div className="w-40">
                <DateField
                    value={filters.to ?? ''}
                    onChange={(iso) => apply({ to: iso || null })}
                />
            </div>

            <Button
                variant="outline"
                size="sm"
                aria-pressed={isAllTime}
                className={cn(isAllTime && 'text-primary border-primary')}
                onClick={() => apply({ all: true })}
            >
                All time
            </Button>
            <Button
                variant="outline"
                size="sm"
                aria-pressed={isThisMonth}
                className={cn(isThisMonth && 'text-primary border-primary')}
                onClick={() => apply({ from: thisFrom, to: thisTo })}
            >
                This month
            </Button>
            <Button
                variant="outline"
                size="sm"
                aria-pressed={isLastMonth}
                className={cn(isLastMonth && 'text-primary border-primary')}
                onClick={() => apply({ from: lastFrom, to: lastTo })}
            >
                Last month
            </Button>

            <div className="ml-auto flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Show only unreconciled"
                    aria-pressed={filters.unreconciled}
                    className={cn(
                        filters.unreconciled && 'text-primary border-primary',
                    )}
                    onClick={() =>
                        apply({ unreconciled: !filters.unreconciled })
                    }
                >
                    <CircleCheck className="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Show only missing invoice"
                    aria-pressed={filters.no_invoice}
                    className={cn(
                        filters.no_invoice && 'text-primary border-primary',
                    )}
                    onClick={() => apply({ no_invoice: !filters.no_invoice })}
                >
                    <FileText className="size-4" />
                </Button>
            </div>
        </div>
    );
}

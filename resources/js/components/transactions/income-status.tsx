import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type IncomeStatus } from './types';

/** The hand-set tags of an income, in the order a deal moves through them. */
export const INCOME_STATUSES: { value: IncomeStatus; label: string }[] = [
    { value: 'agreed', label: 'Agreed' },
    { value: 'invoiced', label: 'Invoiced' },
    { value: 'paid', label: 'Paid' },
    { value: 'dropped', label: 'Dropped' },
];

const STYLES: Record<IncomeStatus, string> = {
    agreed: 'border-sky-600/40 text-sky-700 dark:text-sky-400',
    invoiced: 'border-amber-600/40 text-amber-700 dark:text-amber-400',
    paid: 'border-emerald-600/40 text-emerald-700 dark:text-emerald-400',
    dropped: 'border-border text-muted-foreground',
};

export function incomeStatusLabel(status: IncomeStatus): string {
    return INCOME_STATUSES.find((s) => s.value === status)?.label ?? status;
}

/**
 * An income's status tag, shown in its row. Clicking it opens a small menu to set
 * the tag by hand — it is independent of reconciling.
 */
export function IncomeStatusBadge({
    transactionId,
    status,
}: {
    transactionId: number;
    status: IncomeStatus;
}) {
    function set(next: IncomeStatus) {
        if (next === status) return;
        router.patch(
            `/transactions/${transactionId}/status`,
            { status: next },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    title="Change status"
                    onClick={(e) => e.stopPropagation()}
                    className={cn(
                        // Above the row's stretched open-in-new-tab link.
                        'relative z-10 inline-flex cursor-pointer items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap no-underline',
                        STYLES[status],
                    )}
                >
                    {incomeStatusLabel(status)}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                onClick={(e) => e.stopPropagation()}
            >
                {INCOME_STATUSES.map((s) => (
                    <DropdownMenuItem
                        key={s.value}
                        onSelect={() => set(s.value)}
                    >
                        <Check
                            className={cn(
                                'size-4',
                                s.value !== status && 'invisible',
                            )}
                        />
                        {s.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

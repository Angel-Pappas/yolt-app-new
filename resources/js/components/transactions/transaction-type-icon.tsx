import {
    ArrowDown,
    ArrowLeftRight,
    ArrowUp,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { type TransactionType } from './types';

const TYPE_META: Record<
    TransactionType,
    { label: string; Icon: LucideIcon; className: string }
> = {
    income: {
        label: 'Income',
        Icon: ArrowUp,
        className: 'text-green-600 dark:text-green-500',
    },
    expense: {
        label: 'Expense',
        Icon: ArrowDown,
        className: 'text-red-600 dark:text-red-500',
    },
    transfer: {
        label: 'Transfer',
        Icon: ArrowLeftRight,
        className: 'text-purple-600 dark:text-purple-500',
    },
};

/** The categorical filter options (words) for the Type column. */
export const transactionTypeOptions = (
    Object.keys(TYPE_META) as TransactionType[]
).map((t) => ({ value: t, label: TYPE_META[t].label }));

/**
 * The type of a transaction as a coloured arrow: up/green income, down/red expense,
 * left-right/purple transfer. The word stays in the filter dropdown and the icon's
 * accessible label, so the table reads at a glance without losing meaning.
 */
export function TransactionTypeIcon({ type }: { type: TransactionType }) {
    const meta = TYPE_META[type];
    const Icon = meta.Icon;
    return (
        <span
            className={cn('inline-flex', meta.className)}
            title={meta.label}
            aria-label={meta.label}
        >
            <Icon className="size-4" />
        </span>
    );
}

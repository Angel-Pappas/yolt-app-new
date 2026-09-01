import { type Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A sortable column header: clicking cycles the column's sort. Used as a column's
 * `header` render function — `header: ({ column }) => <ColumnHeader column={column} title="Name" />`.
 */
export function ColumnHeader<TData, TValue>({
    column,
    title,
    align = 'left',
    className,
}: {
    column: Column<TData, TValue>;
    title: string;
    align?: 'left' | 'right' | 'center';
    className?: string;
}) {
    const alignClass =
        align === 'right'
            ? 'w-full justify-end'
            : align === 'center'
              ? 'w-full justify-center'
              : '';

    if (!column.getCanSort()) {
        return (
            <span
                className={cn(
                    align === 'right' && 'block text-right',
                    className,
                )}
            >
                {title}
            </span>
        );
    }

    const sorted = column.getIsSorted();

    return (
        <button
            type="button"
            onClick={() => column.toggleSorting(sorted === 'asc')}
            className={cn(
                'hover:text-foreground -mx-1 flex items-center gap-1 rounded px-1 py-0.5',
                alignClass,
                className,
            )}
        >
            {title}
            {sorted === 'asc' ? (
                <ArrowUp className="size-3.5" />
            ) : sorted === 'desc' ? (
                <ArrowDown className="size-3.5" />
            ) : (
                <ChevronsUpDown className="size-3.5 opacity-50" />
            )}
        </button>
    );
}

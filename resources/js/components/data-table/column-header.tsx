import { type Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ColumnFilter } from './column-filter';

/**
 * A column header: a sortable title (clicking cycles the column's sort) plus, when
 * the column declares `meta.filter`, a funnel filter at the column's trailing edge.
 * Used as a column's `header` render function —
 * `header: ({ column }) => <ColumnHeader column={column} title="Name" />`.
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
    const filterMeta = column.columnDef.meta?.filter;
    const canFilter = column.getCanFilter() && Boolean(filterMeta);
    const canSort = column.getCanSort();
    const sorted = column.getIsSorted();

    // Tri-state cycle: unsorted → ascending → descending → back to the table's
    // default order. No indicator on an unsorted column (only the active sort shows
    // an arrow) to keep the header uncluttered.
    function cycleSort() {
        if (!sorted) column.toggleSorting(false);
        else if (sorted === 'asc') column.toggleSorting(true);
        else column.clearSorting();
    }

    const titleEl = canSort ? (
        <button
            type="button"
            onClick={cycleSort}
            className="hover:text-foreground -mx-1 flex items-center gap-1 rounded px-1 py-0.5"
        >
            {title}
            {sorted === 'asc' ? (
                <ArrowUp className="size-3.5" />
            ) : sorted === 'desc' ? (
                <ArrowDown className="size-3.5" />
            ) : null}
        </button>
    ) : (
        <span>{title}</span>
    );

    return (
        <div
            className={cn(
                'flex items-center gap-0.5',
                align === 'right' && 'justify-end',
                align === 'center' && 'justify-center',
                className,
            )}
        >
            {titleEl}
            {canFilter && filterMeta && (
                <ColumnFilter column={column} meta={filterMeta} />
            )}
        </div>
    );
}

import {
    type ColumnDef,
    type ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    type SortingState,
    useReactTable,
} from '@tanstack/react-table';
import { type ReactNode, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { columnFilterFns, filterFnFor } from './column-filter';

function cellAlign(meta: unknown): string | undefined {
    const align = (meta as { align?: string } | undefined)?.align;
    if (align === 'right') return 'text-right tabular-nums';
    if (align === 'center') return 'text-center';
    return undefined;
}

type Props<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    /** Page heading, rendered at the left of the toolbar row. */
    title?: string;
    /** Placeholder for the global search box; omit to hide the search box. */
    searchPlaceholder?: string;
    /** Extra controls rendered in the header's right cluster, before `action`. */
    toolbar?: ReactNode;
    /** The primary action (e.g. Add button), at the far right of the header row. */
    action?: ReactNode;
    /** An optional secondary controls row rendered under the header row. */
    controls?: ReactNode;
    /** Column filters to seed the table with (e.g. a default exclusion). */
    initialColumnFilters?: ColumnFiltersState;
    /** Called when a row is clicked (e.g. to open it). */
    onRowClick?: (row: TData) => void;
    emptyMessage?: string;
    pageSize?: number;
};

/**
 * The shared, client-side sortable/filterable/searchable/paginated table, built on
 * TanStack Table. Every list in the app renders through this so they look and
 * behave identically: a uniform header row (title · search · actions), optional
 * per-column header filters (declare `meta.filter` on a column), an optional
 * secondary `controls` row, and pagination. Use `ColumnHeader` for headers.
 */
export function DataTable<TData, TValue>({
    columns,
    data,
    title,
    searchPlaceholder,
    toolbar,
    action,
    controls,
    initialColumnFilters,
    onRowClick,
    emptyMessage = 'Nothing here yet.',
    pageSize = 25,
}: Props<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(
        initialColumnFilters ?? [],
    );
    const [globalFilter, setGlobalFilter] = useState('');

    // Give every column that declares a `meta.filter` the matching filter function
    // (and make it filterable), so pages only declare the filter's shape.
    const resolvedColumns = useMemo(
        () =>
            columns.map((column) => {
                const filter = column.meta?.filter;
                if (!filter || column.filterFn) return column;
                return {
                    ...column,
                    enableColumnFilter: true,
                    filterFn: filterFnFor(filter.type),
                };
            }),
        [columns],
    );

    const table = useReactTable({
        data,
        columns: resolvedColumns,
        filterFns: columnFilterFns,
        state: { sorting, columnFilters, globalFilter },
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: { pagination: { pageSize } },
    });

    const showRightCluster = Boolean(searchPlaceholder || toolbar || action);
    const showHeader = Boolean(title || showRightCluster);
    const showPagination = table.getPageCount() > 1;

    return (
        <div className="flex flex-col gap-4">
            {showHeader && (
                <div className="flex flex-wrap items-center gap-2">
                    {title && (
                        <h1 className="text-2xl font-semibold">{title}</h1>
                    )}
                    {showRightCluster && (
                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            {searchPlaceholder && (
                                <Input
                                    placeholder={searchPlaceholder}
                                    value={globalFilter}
                                    onChange={(e) =>
                                        setGlobalFilter(e.target.value)
                                    }
                                    className="w-56"
                                    aria-label="Search"
                                />
                            )}
                            {toolbar}
                            {action}
                        </div>
                    )}
                </div>
            )}

            {controls}

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow
                                key={headerGroup.id}
                                className="bg-muted/50"
                            >
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(row.original)
                                            : undefined
                                    }
                                    className={
                                        onRowClick
                                            ? 'cursor-pointer'
                                            : undefined
                                    }
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn(
                                                cellAlign(
                                                    cell.column.columnDef.meta,
                                                ),
                                            )}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="text-muted-foreground h-24 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {showPagination && (
                <div className="flex items-center justify-between">
                    <div className="text-muted-foreground text-sm">
                        {table.getFilteredRowModel().rows.length} row
                        {table.getFilteredRowModel().rows.length === 1
                            ? ''
                            : 's'}
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="text-muted-foreground text-sm">
                            Page {table.getState().pagination.pageIndex + 1} of{' '}
                            {table.getPageCount()}
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

import {
    type ColumnDef,
    type ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    type SortingState,
    useReactTable,
} from '@tanstack/react-table';
import { type ReactNode, useState } from 'react';
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

function cellAlign(meta: unknown): string | undefined {
    const align = (meta as { align?: string } | undefined)?.align;
    if (align === 'right') return 'text-right tabular-nums';
    if (align === 'center') return 'text-center';
    return undefined;
}

type Props<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    /** Placeholder for the global search box; omit to hide the search box. */
    searchPlaceholder?: string;
    /** Extra filter controls rendered next to the search box. */
    toolbar?: ReactNode;
    /** A row action (e.g. Add button) rendered at the far right of the toolbar. */
    action?: ReactNode;
    /** Called when a row is clicked (e.g. to open it). */
    onRowClick?: (row: TData) => void;
    emptyMessage?: string;
    pageSize?: number;
};

/**
 * The shared, client-side sortable/filterable/searchable/paginated table, built on
 * TanStack Table. Every list in the app renders through this so they look and
 * behave identically. `columns` are standard TanStack `ColumnDef`s; use
 * `ColumnHeader` for sortable headers.
 */
export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder,
    toolbar,
    action,
    onRowClick,
    emptyMessage = 'Nothing here yet.',
    pageSize = 25,
}: Props<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState('');

    const table = useReactTable({
        data,
        columns,
        state: { sorting, columnFilters, globalFilter },
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: { pagination: { pageSize } },
    });

    const showToolbar = Boolean(searchPlaceholder || toolbar || action);
    const showPagination = table.getPageCount() > 1;

    return (
        <div className="flex flex-col gap-4">
            {showToolbar && (
                <div className="flex flex-wrap items-center gap-2">
                    {searchPlaceholder && (
                        <Input
                            placeholder={searchPlaceholder}
                            value={globalFilter}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            className="w-56"
                            aria-label="Search"
                        />
                    )}
                    {toolbar}
                    {action && <div className="ml-auto">{action}</div>}
                </div>
            )}

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

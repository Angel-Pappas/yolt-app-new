import {
    type ColumnDef,
    type ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getSortedRowModel,
    type RowSelectionState,
    type SortingState,
    useReactTable,
} from '@tanstack/react-table';
import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
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
    /**
     * A link target for each row — makes rows openable in a new tab (middle /
     * Ctrl/⌘-click) via a stretched anchor, while a plain left-click still calls
     * `onRowClick`. Interactive cells must be lifted above it (`relative z-10`).
     */
    rowHref?: (row: TData) => string;
    /** Render a totals footer row from each column's `footer` (sums, a count, …). */
    enableTotals?: boolean;
    emptyMessage?: string;
    /** How many rows to render initially and to reveal per scroll step. */
    pageSize?: number;
    /**
     * Enable a leading checkbox column + a bulk-actions bar. Requires `getRowId`.
     * The bar renders when ≥1 row is selected; supply its buttons via
     * `renderBulkActions`, which receives the selected rows and a clear callback.
     */
    enableSelection?: boolean;
    getRowId?: (row: TData) => string;
    renderBulkActions?: (selected: TData[], clear: () => void) => ReactNode;
};

/**
 * The shared, client-side sortable/filterable/searchable table, built on TanStack
 * Table. Every list in the app renders through this so they look and behave
 * identically: a uniform header row (title · search · actions), optional per-column
 * header filters (declare `meta.filter` on a column), and an optional secondary
 * `controls` row. Use `ColumnHeader` for headers.
 *
 * There is no pagination: the table renders the first `pageSize` filtered/sorted
 * rows and reveals another `pageSize` each time a sentinel near the bottom scrolls
 * into view, so the whole list is one continuous scroll. Because the reveal slices
 * TanStack's already-filtered/sorted row model, it works identically under any
 * active filter/search/sort, and resets to the top whenever those change.
 *
 * Sticky top: the header block (title · search · actions, the `controls` row and the
 * bulk-actions bar) is `sticky` to the top of the page, and the table box is its own
 * scroll area, capped to the viewport height left under that block, with a sticky
 * column-header row (and a sticky totals footer). So while scrolling a long list the
 * title, filters and column headers stay in view. The table box has to be the
 * scroller (not the page): its horizontal overflow-scrolling would otherwise trap a
 * sticky `<thead>` against itself instead of the page.
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
    rowHref,
    enableTotals = false,
    emptyMessage = 'Nothing here yet.',
    pageSize = 50,
    enableSelection = false,
    getRowId,
    renderBulkActions,
}: Props<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(
        initialColumnFilters ?? [],
    );
    const [globalFilter, setGlobalFilter] = useState('');
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

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

    // A leading checkbox column: header toggles every row that matches the current
    // filter; each cell toggles its own row. Selection is keyed by row id, so it
    // survives filtering (the bulk bar acts on whatever is selected).
    const tableColumns = useMemo(() => {
        if (!enableSelection) return resolvedColumns;
        const selectColumn: ColumnDef<TData, TValue> = {
            id: '__select',
            enableSorting: false,
            enableGlobalFilter: false,
            meta: { align: 'center' },
            header: ({ table }) => {
                const filtered = table.getFilteredRowModel().rows;
                const all =
                    filtered.length > 0 &&
                    filtered.every((r) => r.getIsSelected());
                const some = filtered.some((r) => r.getIsSelected());
                return (
                    <Checkbox
                        checked={all ? true : some ? 'indeterminate' : false}
                        onCheckedChange={(v) =>
                            table.setRowSelection((old) => {
                                const next = { ...old };
                                filtered.forEach((r) => {
                                    if (v) next[r.id] = true;
                                    else delete next[r.id];
                                });
                                return next;
                            })
                        }
                        aria-label="Select all"
                        onClick={(e) => e.stopPropagation()}
                    />
                );
            },
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(v) => row.toggleSelected(!!v)}
                    aria-label="Select row"
                    onClick={(e) => e.stopPropagation()}
                    // Lift above a row's stretched link overlay so it stays clickable.
                    className="relative z-10"
                />
            ),
        };
        return [selectColumn, ...resolvedColumns];
    }, [enableSelection, resolvedColumns]);

    const table = useReactTable({
        data,
        columns: tableColumns,
        filterFns: columnFilterFns,
        state: { sorting, columnFilters, globalFilter, rowSelection },
        enableRowSelection: enableSelection,
        getRowId,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        onRowSelectionChange: setRowSelection,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
    });

    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((r) => r.original);
    const clearSelection = () => setRowSelection({});

    // Load-as-you-scroll: render only the first `visibleCount` filtered/sorted
    // rows, revealing another `pageSize` whenever the bottom sentinel is in view.
    const rows = table.getRowModel().rows;
    const [visibleCount, setVisibleCount] = useState(pageSize);

    // Reset back to the top whenever the filter/search/sort selection changes, so a
    // freshly filtered list starts short and grows on scroll (render-time reset, no
    // effect — the setState-in-effect anti-pattern).
    const selectionKey = JSON.stringify([sorting, columnFilters, globalFilter]);
    const [prevSelectionKey, setPrevSelectionKey] = useState(selectionKey);
    if (prevSelectionKey !== selectionKey) {
        setPrevSelectionKey(selectionKey);
        setVisibleCount(pageSize);
    }

    const visibleRows = rows.slice(0, visibleCount);
    const hasMore = visibleCount < rows.length;
    const sentinelRef = useRef<HTMLDivElement | null>(null);
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const rootRef = useRef<HTMLDivElement | null>(null);
    const headRef = useRef<HTMLDivElement | null>(null);

    // Publish the sticky header block's live height as `--dt-head`, so the table
    // box can cap itself to the viewport height left beneath it.
    useEffect(() => {
        const root = rootRef.current;
        const head = headRef.current;
        if (!root || !head) return;
        const observer = new ResizeObserver(() =>
            root.style.setProperty('--dt-head', `${head.offsetHeight}px`),
        );
        observer.observe(head);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const el = sentinelRef.current;
        if (!el || !hasMore) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    setVisibleCount((c) => c + pageSize);
                }
            },
            // The rows scroll inside the table box, so watch the sentinel there.
            { root: scrollRef.current, rootMargin: '300px' },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [hasMore, pageSize, visibleCount]);

    const showRightCluster = Boolean(searchPlaceholder || toolbar || action);
    const showHeader = Boolean(title || showRightCluster);

    return (
        <div ref={rootRef} className="flex flex-col">
            {/* Sticky header block; `empty:hidden` drops its padding when a table
                has no header/controls/selection at all. The -mt-2/pt-2 pair keeps a
                little breathing room above the title once it's pinned. */}
            <div
                ref={headRef}
                className="bg-background sticky top-0 z-30 -mt-2 flex flex-col gap-4 pt-2 pb-4 empty:hidden"
            >
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

                {enableSelection && selectedRows.length > 0 && (
                    <div className="bg-muted/50 flex flex-wrap items-center gap-3 rounded-lg border px-3 py-2">
                        <span className="text-sm font-medium">
                            {selectedRows.length} selected
                        </span>
                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            {renderBulkActions?.(selectedRows, clearSelection)}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearSelection}
                            >
                                Clear
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <div
                ref={scrollRef}
                className="max-h-[max(16rem,calc(100svh-var(--dt-head,0px)-4.75rem))] overflow-auto rounded-lg border"
            >
                <Table containerClassName="overflow-visible">
                    {/* Sticky column headers. A collapsed table border doesn't move
                        with a sticky row, so the divider is drawn as a 1px
                        background line on each cell instead. */}
                    <TableHeader className="bg-background sticky top-0 z-20 [&_th]:bg-[linear-gradient(var(--border),var(--border))] [&_th]:bg-[length:100%_1px] [&_th]:bg-bottom [&_th]:bg-no-repeat [&_tr]:border-b-0">
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
                        {visibleRows.length ? (
                            visibleRows.map((row) => {
                                const href = rowHref?.(row.original);
                                return (
                                    <TableRow
                                        key={row.id}
                                        onClick={
                                            !href && onRowClick
                                                ? () => onRowClick(row.original)
                                                : undefined
                                        }
                                        className={cn(
                                            (href || onRowClick) &&
                                                'cursor-pointer',
                                            // Positioning context for the row's
                                            // stretched link overlay.
                                            href && 'relative',
                                        )}
                                    >
                                        {row
                                            .getVisibleCells()
                                            .map((cell, i) => (
                                                <TableCell
                                                    key={cell.id}
                                                    className={cn(
                                                        cellAlign(
                                                            cell.column
                                                                .columnDef.meta,
                                                        ),
                                                    )}
                                                >
                                                    {href && i === 0 && (
                                                        <a
                                                            href={href}
                                                            aria-label="Open"
                                                            className="absolute inset-0"
                                                            onClick={(e) => {
                                                                // Modified clicks
                                                                // (new tab/window)
                                                                // go to the browser;
                                                                // a plain click opens
                                                                // in place.
                                                                if (
                                                                    e.metaKey ||
                                                                    e.ctrlKey ||
                                                                    e.shiftKey ||
                                                                    e.altKey
                                                                )
                                                                    return;
                                                                e.preventDefault();
                                                                onRowClick?.(
                                                                    row.original,
                                                                );
                                                            }}
                                                        />
                                                    )}
                                                    {flexRender(
                                                        cell.column.columnDef
                                                            .cell,
                                                        cell.getContext(),
                                                    )}
                                                </TableCell>
                                            ))}
                                    </TableRow>
                                );
                            })
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={tableColumns.length}
                                    className="text-muted-foreground h-24 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                    {enableTotals && rows.length > 0 && (
                        <TableFooter className="bg-background sticky bottom-0 z-20 border-t-0 [&_td]:bg-[linear-gradient(var(--border),var(--border))] [&_td]:bg-[length:100%_2px] [&_td]:bg-top [&_td]:bg-no-repeat">
                            {table.getFooterGroups().map((footerGroup) => (
                                <TableRow
                                    key={footerGroup.id}
                                    className="bg-muted/50"
                                >
                                    {footerGroup.headers.map((header) => (
                                        <TableCell
                                            key={header.id}
                                            className={cn(
                                                'font-semibold',
                                                cellAlign(
                                                    header.column.columnDef
                                                        .meta,
                                                ),
                                            )}
                                        >
                                            {header.isPlaceholder ||
                                            !header.column.columnDef.footer
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .footer,
                                                      header.getContext(),
                                                  )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableFooter>
                    )}
                </Table>
                {/* Sentinel: when scrolled into view, reveal the next chunk. */}
                {hasMore && <div ref={sentinelRef} aria-hidden="true" />}
            </div>

            {rows.length > 0 && (
                <>
                    <div className="text-muted-foreground mt-4 text-sm">
                        {hasMore
                            ? `Showing ${visibleRows.length} of ${rows.length} rows`
                            : `${rows.length} row${rows.length === 1 ? '' : 's'}`}
                    </div>
                </>
            )}
        </div>
    );
}

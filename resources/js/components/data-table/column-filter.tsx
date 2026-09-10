import {
    type Column,
    type FilterFn,
    type RowData,
} from '@tanstack/react-table';
import { Filter, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { DateField } from '@/components/ui/date-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ColumnFilterType = 'text' | 'select' | 'number' | 'date';

export type ColumnFilterMeta = {
    type: ColumnFilterType;
    /** For `select`; if omitted, options are derived from the column's values. */
    options?: { value: string; label: string }[];
};

// Type the `meta` we attach to columns (align + filter) and name our custom
// filter functions, app-wide.
declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        align?: 'left' | 'right' | 'center';
        filter?: ColumnFilterMeta;
    }
    interface FilterFns {
        selectFilter: FilterFn<unknown>;
        numberRange: FilterFn<unknown>;
        dateRange: FilterFn<unknown>;
    }
}

/** Row value ∈ the selected options (empty selection = no filter). */
const selectFilter: FilterFn<unknown> = (row, columnId, value) => {
    const selected = (value as string[]) ?? [];
    if (selected.length === 0) return true;
    return selected.includes(String(row.getValue(columnId) ?? ''));
};

/** Numeric min/max range. Value is `[min, max]` as strings ('' = open end). */
const numberRange: FilterFn<unknown> = (row, columnId, value) => {
    const [min, max] = (value as [string, string]) ?? ['', ''];
    const n = Number(row.getValue(columnId));
    if (min !== '' && n < Number(min)) return false;
    if (max !== '' && n > Number(max)) return false;
    return true;
};

/** ISO date from/to range. Value is `[from, to]` (yyyy-mm-dd sorts lexically). */
const dateRange: FilterFn<unknown> = (row, columnId, value) => {
    const [from, to] = (value as [string, string]) ?? ['', ''];
    const v = String(row.getValue(columnId) ?? '');
    if (!v) return false;
    if (from && v < from) return false;
    if (to && v > to) return false;
    return true;
};

/** The custom filter functions, registered on every DataTable. */
export const columnFilterFns = { selectFilter, numberRange, dateRange };

/** The TanStack `filterFn` a column of the given type should use. */
export function filterFnFor(type: ColumnFilterType) {
    switch (type) {
        case 'text':
            return 'includesString' as const;
        case 'select':
            return 'selectFilter' as const;
        case 'number':
            return 'numberRange' as const;
        case 'date':
            return 'dateRange' as const;
    }
}

/**
 * The per-column header filter: a funnel button opening a popover whose body
 * depends on the column's `meta.filter.type`. Rendered by `ColumnHeader` for any
 * filterable column, so every table gets identical header filters. The popover
 * portals to the body (Radix), so a table's overflow never clips it.
 */
export function ColumnFilter<TData, TValue>({
    column,
    meta,
}: {
    column: Column<TData, TValue>;
    meta: ColumnFilterMeta;
}) {
    const active = column.getIsFiltered();

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'size-6 shrink-0',
                        active
                            ? 'text-primary'
                            : 'text-muted-foreground/60 hover:text-foreground',
                    )}
                    aria-label="Filter column"
                    aria-pressed={active}
                    onClick={(e) => e.stopPropagation()}
                >
                    <Filter
                        className={cn('size-3.5', active && 'fill-current')}
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-56 p-0"
                onClick={(e) => e.stopPropagation()}
            >
                <FilterBody column={column} meta={meta} />
            </PopoverContent>
        </Popover>
    );
}

function FilterBody<TData, TValue>({
    column,
    meta,
}: {
    column: Column<TData, TValue>;
    meta: ColumnFilterMeta;
}) {
    if (meta.type === 'select')
        return <SelectBody column={column} meta={meta} />;
    if (meta.type === 'number') return <NumberBody column={column} />;
    if (meta.type === 'date') return <DateBody column={column} />;
    return <TextBody column={column} />;
}

function ClearRow({ onClear }: { onClear: () => void }) {
    return (
        <button
            type="button"
            onClick={onClear}
            className="text-muted-foreground hover:text-foreground flex w-full items-center gap-1 border-t px-3 py-2 text-xs"
        >
            <X className="size-3" />
            Clear filter
        </button>
    );
}

function TextBody<TData, TValue>({
    column,
}: {
    column: Column<TData, TValue>;
}) {
    const value = (column.getFilterValue() as string) ?? '';
    return (
        <div>
            <div className="p-2">
                <Input
                    autoFocus
                    value={value}
                    placeholder="Contains…"
                    onChange={(e) =>
                        column.setFilterValue(e.target.value || undefined)
                    }
                    className="h-8"
                />
            </div>
            {value !== '' && (
                <ClearRow onClear={() => column.setFilterValue(undefined)} />
            )}
        </div>
    );
}

function SelectBody<TData, TValue>({
    column,
    meta,
}: {
    column: Column<TData, TValue>;
    meta: ColumnFilterMeta;
}) {
    const selected = (column.getFilterValue() as string[]) ?? [];
    const options =
        meta.options ??
        Array.from(column.getFacetedUniqueValues().keys())
            .filter((v) => v != null && v !== '')
            .map((v) => String(v))
            .sort((a, b) => a.localeCompare(b))
            .map((v) => ({ value: v, label: v }));

    function toggle(value: string) {
        const set = new Set(selected);
        if (set.has(value)) set.delete(value);
        else set.add(value);
        const next = Array.from(set);
        column.setFilterValue(next.length ? next : undefined);
    }

    return (
        <div>
            <Command>
                <CommandInput placeholder="Search…" className="h-9" />
                <CommandList>
                    <CommandEmpty>No options.</CommandEmpty>
                    <CommandGroup>
                        {options.map((option) => (
                            <CommandItem
                                key={option.value}
                                value={option.label}
                                onSelect={() => toggle(option.value)}
                            >
                                <Checkbox
                                    checked={selected.includes(option.value)}
                                    className="mr-2"
                                />
                                {option.label}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </Command>
            {selected.length > 0 && (
                <ClearRow onClear={() => column.setFilterValue(undefined)} />
            )}
        </div>
    );
}

function NumberBody<TData, TValue>({
    column,
}: {
    column: Column<TData, TValue>;
}) {
    const [min, max] = (column.getFilterValue() as [string, string]) ?? [
        '',
        '',
    ];

    function set(nextMin: string, nextMax: string) {
        column.setFilterValue(
            nextMin || nextMax ? [nextMin, nextMax] : undefined,
        );
    }

    return (
        <div>
            <div className="grid grid-cols-2 gap-2 p-2">
                <div className="grid gap-1">
                    <Label className="text-muted-foreground text-xs">Min</Label>
                    <Input
                        inputMode="decimal"
                        value={min}
                        onChange={(e) => set(e.target.value, max)}
                        className="h-8"
                    />
                </div>
                <div className="grid gap-1">
                    <Label className="text-muted-foreground text-xs">Max</Label>
                    <Input
                        inputMode="decimal"
                        value={max}
                        onChange={(e) => set(min, e.target.value)}
                        className="h-8"
                    />
                </div>
            </div>
            {(min || max) && (
                <ClearRow onClear={() => column.setFilterValue(undefined)} />
            )}
        </div>
    );
}

function DateBody<TData, TValue>({
    column,
}: {
    column: Column<TData, TValue>;
}) {
    const [from, to] = (column.getFilterValue() as [string, string]) ?? [
        '',
        '',
    ];

    function set(nextFrom: string, nextTo: string) {
        column.setFilterValue(
            nextFrom || nextTo ? [nextFrom, nextTo] : undefined,
        );
    }

    return (
        <div>
            <div className="grid gap-2 p-2">
                <div className="grid gap-1">
                    <Label className="text-muted-foreground text-xs">
                        From
                    </Label>
                    <DateField value={from} onChange={(iso) => set(iso, to)} />
                </div>
                <div className="grid gap-1">
                    <Label className="text-muted-foreground text-xs">To</Label>
                    <DateField value={to} onChange={(iso) => set(from, iso)} />
                </div>
            </div>
            {(from || to) && (
                <ClearRow onClear={() => column.setFilterValue(undefined)} />
            )}
        </div>
    );
}

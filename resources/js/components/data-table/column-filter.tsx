import {
    type Column,
    type FilterFn,
    type RowData,
} from '@tanstack/react-table';
import { Check, Filter, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
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
 * filterable column, so every table gets identical header filters. Each body owns
 * local state (seeded from the column's current value on open) and writes through
 * to `column.setFilterValue`, so the controls always reflect input instantly. The
 * popover portals to the body (Radix), so a table's overflow never clips it.
 */
export function ColumnFilter<TData, TValue>({
    column,
    meta,
}: {
    column: Column<TData, TValue>;
    meta: ColumnFilterMeta;
}) {
    const [open, setOpen] = useState(false);
    const active = column.getIsFiltered();

    return (
        <Popover open={open} onOpenChange={setOpen}>
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
                >
                    <Filter
                        className={cn('size-3.5', active && 'fill-current')}
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-56 p-0">
                {/* Bodies mount fresh each open (Radix unmounts closed content),
                    so their local state seeds from the current filter value. */}
                {meta.type === 'select' ? (
                    <SelectBody column={column} meta={meta} />
                ) : meta.type === 'number' ? (
                    <NumberBody column={column} />
                ) : meta.type === 'date' ? (
                    <DateBody column={column} />
                ) : (
                    <TextBody column={column} />
                )}
            </PopoverContent>
        </Popover>
    );
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
    const [value, setValue] = useState(
        (column.getFilterValue() as string) ?? '',
    );

    function set(next: string) {
        setValue(next);
        column.setFilterValue(next || undefined);
    }

    return (
        <div>
            <div className="p-2">
                <Input
                    autoFocus
                    value={value}
                    placeholder="Contains…"
                    onChange={(e) => set(e.target.value)}
                    className="h-8"
                />
            </div>
            {value !== '' && <ClearRow onClear={() => set('')} />}
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
    const [selected, setSelected] = useState<string[]>(
        (column.getFilterValue() as string[]) ?? [],
    );
    const [query, setQuery] = useState('');

    // Options come from the column config, else the column's own distinct values.
    const facets = column.getFacetedUniqueValues();
    const options = useMemo(
        () =>
            meta.options ??
            Array.from(facets.keys())
                .filter((v) => v != null && v !== '')
                .map((v) => String(v))
                .sort((a, b) => a.localeCompare(b))
                .map((v) => ({ value: v, label: v })),
        [meta.options, facets],
    );

    const term = query.trim().toLowerCase();
    const shown = term
        ? options.filter((o) => o.label.toLowerCase().includes(term))
        : options;

    function commit(next: string[]) {
        setSelected(next);
        column.setFilterValue(next.length ? next : undefined);
    }

    function toggle(value: string) {
        commit(
            selected.includes(value)
                ? selected.filter((v) => v !== value)
                : [...selected, value],
        );
    }

    return (
        <div>
            <div className="border-b p-2">
                <Input
                    autoFocus
                    value={query}
                    placeholder="Search…"
                    onChange={(e) => setQuery(e.target.value)}
                    className="h-8"
                />
            </div>
            <div className="max-h-64 overflow-y-auto p-1">
                {shown.length === 0 ? (
                    <div className="text-muted-foreground py-6 text-center text-sm">
                        No options.
                    </div>
                ) : (
                    shown.map((option) => {
                        const isSelected = selected.includes(option.value);
                        return (
                            <button
                                type="button"
                                key={option.value}
                                onClick={() => toggle(option.value)}
                                className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                            >
                                <span
                                    className={cn(
                                        'flex size-4 shrink-0 items-center justify-center rounded-[4px] border',
                                        isSelected
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input',
                                    )}
                                >
                                    {isSelected && (
                                        <Check className="size-3.5" />
                                    )}
                                </span>
                                <span className="truncate">{option.label}</span>
                            </button>
                        );
                    })
                )}
            </div>
            {selected.length > 0 && <ClearRow onClear={() => commit([])} />}
        </div>
    );
}

function NumberBody<TData, TValue>({
    column,
}: {
    column: Column<TData, TValue>;
}) {
    const initial = (column.getFilterValue() as [string, string]) ?? ['', ''];
    const [min, setMin] = useState(initial[0]);
    const [max, setMax] = useState(initial[1]);

    function set(nextMin: string, nextMax: string) {
        setMin(nextMin);
        setMax(nextMax);
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
            {(min || max) && <ClearRow onClear={() => set('', '')} />}
        </div>
    );
}

function DateBody<TData, TValue>({
    column,
}: {
    column: Column<TData, TValue>;
}) {
    const initial = (column.getFilterValue() as [string, string]) ?? ['', ''];
    const [from, setFrom] = useState(initial[0]);
    const [to, setTo] = useState(initial[1]);

    function set(nextFrom: string, nextTo: string) {
        setFrom(nextFrom);
        setTo(nextTo);
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
            {(from || to) && <ClearRow onClear={() => set('', '')} />}
        </div>
    );
}

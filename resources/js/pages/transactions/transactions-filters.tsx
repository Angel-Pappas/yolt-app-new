import { router } from '@inertiajs/react';
import { CircleCheck, FileText, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Option = { id: number; name: string };

export type TransactionFilters = {
    q: string | null;
    type: string | null;
    wallet: number | null;
    from: string | null;
    to: string | null;
    unreconciled: boolean;
    no_invoice: boolean;
    all: boolean;
};

type Props = {
    filters: TransactionFilters;
    wallets: Option[];
    hideWallet?: boolean;
};

const ALL = 'all';

export function TransactionsFilters({
    filters,
    wallets,
    hideWallet = false,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

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
        if (merged.q) params.q = merged.q;
        if (merged.type) params.type = merged.type;
        if (merged.wallet) params.wallet = String(merged.wallet);
        if (merged.from) params.from = merged.from;
        if (merged.to) params.to = merged.to;
        if (merged.unreconciled) params.unreconciled = '1';
        if (merged.no_invoice) params.no_invoice = '1';
        if (merged.all) params.all = '1';

        router.get('/transactions', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function onSearchChange(value: string) {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value || null }), 300);
    }

    function clearAll() {
        setSearch('');
        router.get(
            '/transactions',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const hasFilters = Boolean(
        filters.q ||
        filters.type ||
        filters.wallet ||
        filters.from ||
        filters.to ||
        filters.unreconciled ||
        filters.no_invoice,
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input
                placeholder="Search…"
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                className="w-48"
                aria-label="Search transactions"
            />

            <Select
                value={filters.type ?? ALL}
                onValueChange={(v) => apply({ type: v === ALL ? null : v })}
            >
                <SelectTrigger className="w-36">
                    <SelectValue placeholder="All types" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All types</SelectItem>
                    <SelectItem value="income">Income</SelectItem>
                    <SelectItem value="expense">Expense</SelectItem>
                    <SelectItem value="transfer">Transfer</SelectItem>
                </SelectContent>
            </Select>

            {!hideWallet && (
                <Select
                    value={filters.wallet ? String(filters.wallet) : ALL}
                    onValueChange={(v) =>
                        apply({ wallet: v === ALL ? null : Number(v) })
                    }
                >
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="All wallets" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>All wallets</SelectItem>
                        {wallets.map((wallet) => (
                            <SelectItem
                                key={wallet.id}
                                value={String(wallet.id)}
                            >
                                {wallet.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

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
                aria-pressed={filters.all}
                className={cn(filters.all && 'text-primary border-primary')}
                onClick={() => apply({ all: !filters.all })}
            >
                All time
            </Button>

            <Button
                variant="outline"
                size="icon"
                aria-label="Show only unreconciled"
                aria-pressed={filters.unreconciled}
                className={cn(
                    filters.unreconciled && 'text-primary border-primary',
                )}
                onClick={() => apply({ unreconciled: !filters.unreconciled })}
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

            {hasFilters && (
                <Button variant="ghost" size="sm" onClick={clearAll}>
                    <X className="size-4" />
                    Clear
                </Button>
            )}
        </div>
    );
}

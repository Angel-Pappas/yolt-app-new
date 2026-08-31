import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { id: number; name: string };

export type TransactionFilters = {
    q: string | null;
    type: string | null;
    wallet: number | null;
    from: string | null;
    to: string | null;
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
        const params: Record<string, string> = {};
        if (merged.q) params.q = merged.q;
        if (merged.type) params.type = merged.type;
        if (merged.wallet) params.wallet = String(merged.wallet);
        if (merged.from) params.from = merged.from;
        if (merged.to) params.to = merged.to;

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
        filters.to,
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

            <Input
                type="date"
                value={filters.from ?? ''}
                onChange={(e) => apply({ from: e.target.value || null })}
                className="w-40"
                aria-label="From date"
            />
            <Input
                type="date"
                value={filters.to ?? ''}
                onChange={(e) => apply({ to: e.target.value || null })}
                className="w-40"
                aria-label="To date"
            />

            {hasFilters && (
                <Button variant="ghost" size="sm" onClick={clearAll}>
                    <X className="size-4" />
                    Clear
                </Button>
            )}
        </div>
    );
}

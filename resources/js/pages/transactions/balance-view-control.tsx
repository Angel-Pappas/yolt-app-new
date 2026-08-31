import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TransactionFilters } from './transactions-filters';

type Option = { id: number; name: string };

type Props = {
    wallets: Option[];
    filters: TransactionFilters;
    active: { wallet_id: number; wallet_name: string } | null;
};

/**
 * Enter/exit the per-wallet balance view. Entering sets `?balance=<id>` while
 * preserving the active search/type/date filters; the wallet filter is dropped
 * (the list is already scoped to the balance wallet).
 */
export function BalanceViewControl({ wallets, filters, active }: Props) {
    function baseParams(): Record<string, string> {
        const p: Record<string, string> = {};
        if (filters.q) p.q = filters.q;
        if (filters.type) p.type = filters.type;
        if (filters.from) p.from = filters.from;
        if (filters.to) p.to = filters.to;
        return p;
    }

    function enter(id: string) {
        router.get(
            '/transactions',
            { ...baseParams(), balance: id },
            { preserveScroll: true },
        );
    }

    function exit() {
        router.get('/transactions', baseParams(), { preserveScroll: true });
    }

    if (active) {
        return (
            <div className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm">
                <span className="text-muted-foreground">Balance:</span>
                <span className="font-medium">{active.wallet_name}</span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-6"
                    onClick={exit}
                    aria-label="Exit balance view"
                >
                    <X className="size-4" />
                </Button>
            </div>
        );
    }

    return (
        <Select value="" onValueChange={enter}>
            <SelectTrigger className="w-40">
                <SelectValue placeholder="Balance view" />
            </SelectTrigger>
            <SelectContent>
                {wallets.map((wallet) => (
                    <SelectItem key={wallet.id} value={String(wallet.id)}>
                        {wallet.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

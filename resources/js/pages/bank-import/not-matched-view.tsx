import { router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CalendarClock, Ellipsis, Link2, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { toast } from 'sonner';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type AppRow, nextMonth, whoAndWhat } from '@/lib/bank-import';
import { formatAmount, formatDate } from '@/lib/format';

export type MatchItem = {
    key: string;
    kind: 'recurrence' | 'tax' | 'one-off' | 'payment';
    id: number | null;
    date: string;
    description: string;
    entity: string | null;
    type: string;
    open: number;
    total: number;
    wallet: string;
    /** Real rows that could be its payment. */
    candidates: AppRow[];
};

const KIND: Record<MatchItem['kind'], string> = {
    recurrence: 'Recurring',
    tax: 'Tax',
    'one-off': 'One-off',
    payment: 'Customer payment',
};

const send = (url: string, data: Record<string, unknown>) =>
    router.post(url, data as never, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? 'Not saved.'),
    });

/**
 * Bank import ▸ Not matched yet: planned items dated in a concluded period that no
 * real transaction paid. Each still counts in the projection, from today. Link one
 * to the transaction that was its payment, move it to when it is now expected, or
 * cancel it.
 */
export function NotMatchedView({
    items,
    heading,
}: {
    items: MatchItem[];
    heading: ReactNode;
}) {
    const [moving, setMoving] = useState<{
        item: MatchItem;
        date: string;
    } | null>(null);

    const columns: ColumnDef<MatchItem>[] = [
        {
            id: 'date',
            accessorFn: (r) => r.date,
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Was due" />
            ),
            cell: ({ row }) => formatDate(row.original.date),
        },
        {
            id: 'kind',
            accessorFn: (r) => KIND[r.kind],
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="What" />
            ),
        },
        {
            id: 'entity',
            accessorFn: (r) => r.entity ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => row.original.entity ?? '—',
        },
        {
            id: 'description',
            accessorFn: (r) => r.description,
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'wallet',
            accessorFn: (r) => r.wallet,
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Wallet" />
            ),
        },
        {
            id: 'open',
            accessorFn: (r) => r.open,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Open" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.open),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="relative z-10 flex justify-end gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setMoving({
                                    item,
                                    date: nextMonth(
                                        new Date().toISOString().slice(0, 10),
                                    ),
                                })
                            }
                            title="Move it to when it is now expected"
                        >
                            <CalendarClock className="size-4" />
                            Move
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="More choices"
                                >
                                    <Ellipsis className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-80">
                                <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                    <Link2 className="mr-1 inline size-3.5" />
                                    It was paid by…
                                </DropdownMenuLabel>
                                {item.candidates.length === 0 && (
                                    <DropdownMenuItem disabled>
                                        No transaction of {item.entity ?? 'it'}{' '}
                                        nearby
                                    </DropdownMenuItem>
                                )}
                                {item.candidates.map((c) => (
                                    <DropdownMenuItem
                                        key={c.id}
                                        onSelect={() =>
                                            send('/plan-items/link', {
                                                key: item.key,
                                                transaction_id: c.id,
                                            })
                                        }
                                    >
                                        <span className="min-w-0 flex-1 truncate">
                                            {formatDate(c.date)}{' '}
                                            {c.description || whoAndWhat(c)}
                                        </span>
                                        <span className="tabular-nums">
                                            {formatAmount(c.amount)}
                                        </span>
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => {
                                        if (
                                            window.confirm(
                                                'Cancel it? It will not happen and stops counting.',
                                            )
                                        ) {
                                            send('/plan-items/cancel', {
                                                key: item.key,
                                            });
                                        }
                                    }}
                                >
                                    <X className="size-4" />
                                    Cancel it — it won't happen
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <DataTable
                columns={columns}
                data={items}
                title="Not matched yet"
                heading={heading}
                emptyMessage="Everything planned in a concluded period is matched."
                controls={
                    <p className="text-muted-foreground text-sm">
                        Planned items whose day is in a concluded period but
                        that nothing paid. Each still counts in the projection,
                        from today, until you link, move or cancel it.
                    </p>
                }
            />
            <Dialog
                open={moving !== null}
                onOpenChange={(open) => !open && setMoving(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Move to a new date</DialogTitle>
                        <DialogDescription>
                            {moving &&
                                `${moving.item.description || moving.item.entity || 'It'} — ${formatAmount(moving.item.open)} — is now expected on:`}
                        </DialogDescription>
                    </DialogHeader>
                    {moving && (
                        <DateField
                            value={moving.date}
                            showCalendar={false}
                            onChange={(v) => setMoving({ ...moving, date: v })}
                        />
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setMoving(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!moving?.date}
                            onClick={() => {
                                if (!moving) return;
                                send('/plan-items/move', {
                                    key: moving.item.key,
                                    date: moving.date,
                                });
                                setMoving(null);
                            }}
                        >
                            Move
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

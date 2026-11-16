import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Check, CircleAlert, Ellipsis, Undo2, Upload } from 'lucide-react';
import { type ChangeEvent, useRef, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable, DONE_ROW_CLASS } from '@/components/data-table/data-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useStoredState } from '@/hooks/use-stored-state';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    type BankLinePrefill,
    type BankLineTemplate,
    TransactionFormDialog,
} from '@/pages/transactions/transaction-form-dialog';

type Summary = {
    id: number;
    date: string;
    type: string;
    entity: string | null;
    category: string | null;
    description: string;
    amount: number;
    is_reconciled: boolean;
};

type Candidate = Summary & { exact: boolean };

type Template = Summary &
    BankLineTemplate & {
        to_wallet: string | null;
    };

type Proposal =
    | { kind: 'match'; transaction_id: number; template: null }
    | { kind: 'fee'; transaction_id: null; template: null }
    | { kind: 'like'; transaction_id: number; template: Template | null };

type Line = {
    id: number;
    date: string;
    position: number;
    text: string;
    amount: number;
    status: 'pending' | 'linked' | 'dismissed';
    transaction: Summary | null;
    candidates: Candidate[];
    proposal: Proposal | null;
};

type WalletInfo = {
    id: number;
    name: string;
    takes_fees: boolean;
    statement_balance: string | null;
    statement_date: string | null;
    app_balance: number | null;
    not_in_bank: { count: number; from: string; to: string } | null;
};

type Props = {
    wallets: { id: number; name: string }[];
    wallet: WalletInfo | null;
    lines: Line[];
};

/** "Κώνστας Γιώργος · Contractors" — who and what a row is. */
function label(t: Summary | Template): string {
    const who =
        t.type === 'transfer'
            ? `Transfer${'to_wallet' in t && t.to_wallet ? ` ↔ ${t.to_wallet}` : ''}`
            : (t.entity ?? 'No entity');
    return t.category ? `${who} · ${t.category}` : who;
}

/** What applying a line's suggestion will do, in words. */
function proposalText(line: Line): string | null {
    const p = line.proposal;
    if (!p) return null;
    if (p.kind === 'fee') return 'Add as bank fee';
    if (p.kind === 'like') {
        const t = p.template;
        if (!t) return 'Add new';
        // Money back from a supplier: an income from it, without its category.
        const flips =
            t.type !== 'transfer' && (t.type === 'income') !== line.amount > 0;
        return flips
            ? `Add new: income from ${t.entity ?? 'no entity'}`
            : `Add new: ${label(t)}`;
    }
    const t = line.candidates.find((c) => c.id === p.transaction_id);
    if (!t) return 'Confirm a row';
    // Only what differs from the line itself: its date, and the amount the row
    // will change to.
    const parts = [`Confirm ${label(t)}`];
    if (t.date !== line.date) parts.push(`of ${formatDate(t.date)}`);
    if (Math.abs(t.amount - Math.abs(line.amount)) >= 0.005) {
        parts.push(
            `· ${formatAmount(t.amount)} → ${formatAmount(Math.abs(line.amount))}`,
        );
    }
    return parts.join(' ');
}

/**
 * Finance ▸ Bank import. Upload a wallet's statement (Alpha Bank CSV); each line then
 * waits here until it confirms a row the app already has, becomes a new one, or is
 * set aside. The app suggests what each line is; apply one, select several and apply
 * them together, or choose by hand from the ⋯ menu. Done lines show (green) with
 * "Show all". The check above the list compares the bank's closing balance with the
 * app's checked balance on that day.
 */
export default function BankImportIndex({ wallets, wallet, lines }: Props) {
    const [showAll, setShowAll] = useStoredState('bank-import-show-all', false);
    const [adding, setAdding] = useState<BankLinePrefill | null>(null);
    const [addKey, setAddKey] = useState(0);
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const rows = showAll ? lines : lines.filter((l) => l.status === 'pending');
    const pendingCount = lines.filter((l) => l.status === 'pending').length;

    function upload(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !wallet) return;
        router.post(
            '/bank-import',
            { wallet_id: wallet.id, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
            },
        );
    }

    const post = (url: string, data: Record<string, number> = {}) =>
        router.post(url, data, { preserveScroll: true, preserveState: true });

    function apply(line: Line) {
        const p = line.proposal;
        if (!p) return;
        if (p.kind === 'match') {
            post(`/bank-lines/${line.id}/match`, {
                transaction_id: p.transaction_id,
            });
        } else if (p.kind === 'fee') {
            post(`/bank-lines/${line.id}/fee`);
        } else {
            post(`/bank-lines/${line.id}/like`, {
                transaction_id: p.transaction_id,
            });
        }
    }

    function openAdd(line: Line) {
        if (!wallet) return;
        setAddKey((k) => k + 1);
        setAdding({
            id: line.id,
            date: line.date,
            amount: line.amount,
            text: line.text,
            wallet_id: wallet.id,
            template:
                line.proposal?.kind === 'like' ? line.proposal.template : null,
        });
    }

    const columns: ColumnDef<Line>[] = [
        {
            accessorKey: 'date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => formatDate(row.original.date),
        },
        {
            accessorKey: 'text',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Bank text" />
            ),
            cell: ({ row }) => (
                <span className="break-words">{row.original.text}</span>
            ),
        },
        {
            accessorKey: 'amount',
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Amount" align="right" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'font-medium whitespace-nowrap',
                        row.original.amount > 0 &&
                            'text-emerald-700 dark:text-emerald-400',
                    )}
                >
                    {row.original.amount > 0 ? '+' : '−'}
                    {formatAmount(Math.abs(row.original.amount))}
                </span>
            ),
        },
        {
            id: 'app',
            accessorFn: (l) =>
                l.transaction ? label(l.transaction) : (proposalText(l) ?? ''),
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="In the app" />
            ),
            cell: ({ row }) => {
                const l = row.original;
                if (l.status === 'linked' && l.transaction) {
                    return (
                        <Link
                            href={`/transactions?edit=${l.transaction.id}`}
                            className="relative z-10 hover:underline"
                        >
                            {label(l.transaction)}
                        </Link>
                    );
                }
                if (l.status === 'dismissed') {
                    return <span className="italic">Set aside</span>;
                }
                const text = proposalText(l);
                return text ? (
                    <span className="text-muted-foreground">{text}</span>
                ) : (
                    <span className="text-muted-foreground inline-flex items-center gap-1">
                        <CircleAlert className="size-3.5 text-amber-600" />
                        Choose from ⋯
                    </span>
                );
            },
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => {
                const l = row.original;
                if (l.status !== 'pending') {
                    return (
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Undo — the line waits again (the transaction stays)"
                            aria-label="Undo"
                            onClick={() =>
                                l.status === 'linked'
                                    ? post(`/bank-lines/${l.id}/unlink`)
                                    : router.patch(
                                          `/bank-lines/${l.id}/dismiss`,
                                          { dismissed: false },
                                          { preserveScroll: true },
                                      )
                            }
                        >
                            <Undo2 className="size-4" />
                        </Button>
                    );
                }
                return (
                    <div className="flex justify-end gap-1">
                        {l.proposal && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => apply(l)}
                                title={proposalText(l) ?? undefined}
                            >
                                <Check className="size-4" />
                                Apply
                            </Button>
                        )}
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
                            <DropdownMenuContent align="end" className="w-72">
                                {l.candidates.length > 0 ? (
                                    <DropdownMenuSub>
                                        <DropdownMenuSubTrigger>
                                            Confirm a row
                                        </DropdownMenuSubTrigger>
                                        <DropdownMenuSubContent className="w-80">
                                            <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                                Takes the bank's date and amount
                                            </DropdownMenuLabel>
                                            {l.candidates.map((c) => (
                                                <DropdownMenuItem
                                                    key={c.id}
                                                    onSelect={() =>
                                                        post(
                                                            `/bank-lines/${l.id}/match`,
                                                            {
                                                                transaction_id:
                                                                    c.id,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {formatDate(c.date)}{' '}
                                                        {label(c)}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'tabular-nums',
                                                            !c.exact &&
                                                                'text-amber-600',
                                                        )}
                                                    >
                                                        {formatAmount(c.amount)}
                                                    </span>
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                ) : (
                                    <DropdownMenuItem disabled>
                                        No row to confirm nearby
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem onSelect={() => openAdd(l)}>
                                    Add new…
                                </DropdownMenuItem>
                                {wallet?.takes_fees && l.amount < 0 && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            post(`/bank-lines/${l.id}/fee`)
                                        }
                                    >
                                        Add as bank fee
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.patch(
                                            `/bank-lines/${l.id}/dismiss`,
                                            { dismissed: true },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Set aside (handled another way)
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    const balanceDiff =
        wallet?.statement_balance != null && wallet.app_balance != null
            ? Math.round(
                  (Number(wallet.statement_balance) - wallet.app_balance) * 100,
              ) / 100
            : null;

    return (
        <>
            <Head title="Bank import" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={rows}
                    title="Bank import"
                    stateKey={`bank-import-${wallet?.id ?? 0}`}
                    searchPlaceholder="Search lines…"
                    emptyMessage={
                        lines.length === 0
                            ? 'No statement imported yet — upload the bank’s CSV export.'
                            : 'Every line is done.'
                    }
                    rowClassName={(l) =>
                        l.status === 'pending' ? undefined : DONE_ROW_CLASS
                    }
                    enableSelection
                    getRowId={(l) => String(l.id)}
                    renderBulkActions={(selected, clear) => {
                        const ids = selected
                            .filter((l) => l.status === 'pending' && l.proposal)
                            .map((l) => l.id);
                        return (
                            <Button
                                size="sm"
                                disabled={ids.length === 0}
                                onClick={() =>
                                    router.post(
                                        '/bank-lines/apply',
                                        { ids },
                                        {
                                            preserveScroll: true,
                                            onSuccess: clear,
                                        },
                                    )
                                }
                            >
                                <Check className="size-4" />
                                Apply {ids.length}{' '}
                                {ids.length === 1
                                    ? 'suggestion'
                                    : 'suggestions'}
                            </Button>
                        );
                    }}
                    toolbar={
                        wallets.length > 1 && wallet ? (
                            <Select
                                value={String(wallet.id)}
                                onValueChange={(v) =>
                                    router.get('/bank-import', { wallet: v })
                                }
                            >
                                <SelectTrigger
                                    className="w-40"
                                    aria-label="Wallet"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {wallets.map((w) => (
                                        <SelectItem
                                            key={w.id}
                                            value={String(w.id)}
                                        >
                                            {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : undefined
                    }
                    action={
                        <>
                            <input
                                ref={fileInput}
                                type="file"
                                accept=".csv,text/csv"
                                className="hidden"
                                onChange={upload}
                            />
                            <Button
                                onClick={() => fileInput.current?.click()}
                                disabled={!wallet || uploading}
                                size="icon"
                                aria-label="Upload statement"
                                title="Upload statement (CSV)"
                            >
                                <Upload className="size-4" />
                            </Button>
                        </>
                    }
                    controls={
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            {wallet?.statement_date && balanceDiff !== null ? (
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5',
                                        balanceDiff === 0
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : 'text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {balanceDiff === 0 ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <CircleAlert className="size-4" />
                                    )}
                                    Bank {formatDate(wallet.statement_date)}:{' '}
                                    {formatAmount(
                                        Number(wallet.statement_balance),
                                    )}{' '}
                                    · App:{' '}
                                    {formatAmount(wallet.app_balance ?? 0)}
                                    {balanceDiff !== 0 &&
                                        ` · difference ${formatAmount(Math.abs(balanceDiff))}`}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    No statement uploaded for this wallet yet.
                                </span>
                            )}
                            {wallet?.not_in_bank &&
                                wallet.not_in_bank.count > 0 && (
                                    <Link
                                        className="text-muted-foreground hover:text-foreground underline-offset-4 hover:underline"
                                        href={`/transactions?balance=${wallet.id}&unreconciled=1&from=${wallet.not_in_bank.from}&to=${wallet.not_in_bank.to}`}
                                        title="Unreconciled rows of this wallet in the statement's dates — a leftover automatic row next to one typed by hand, or a payment that hasn't happened"
                                    >
                                        {wallet.not_in_bank.count} in the app,
                                        not at the bank →
                                    </Link>
                                )}
                            {pendingCount > 0 && showAll && (
                                <span className="text-muted-foreground">
                                    {pendingCount} to do
                                </span>
                            )}
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                />
            </div>

            <TransactionFormDialog
                key={`bank-add-${addKey}`}
                open={adding !== null}
                onOpenChange={(open) => !open && setAdding(null)}
                bankLine={adding}
            />
        </>
    );
}

BankImportIndex.layout = {
    breadcrumbs: [{ title: 'Bank import', href: '/bank-import' }],
};

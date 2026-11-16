import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { endOfMonth, format, startOfMonth, subMonths } from 'date-fns';
import {
    Check,
    CircleAlert,
    CircleCheck,
    CircleX,
    Ellipsis,
    GripVertical,
    Plus,
    Upload,
} from 'lucide-react';
import {
    type ChangeEvent,
    type DragEvent,
    type ReactNode,
    useRef,
    useState,
} from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
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
import { useIsMobile } from '@/hooks/use-mobile';
import {
    type AppRow,
    pairOptions,
    signedCash,
    whoAndWhat,
} from '@/lib/bank-import';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    type BankLinePrefill,
    type BankLineTemplate,
    TransactionFormDialog,
} from '@/pages/transactions/transaction-form-dialog';

type Template = AppRow & BankLineTemplate;

type Status = 'match' | 'differs' | 'bank_only' | 'app_only';

type Row = {
    key: string;
    status: Status;
    line: { id: number; date: string; text: string; amount: number } | null;
    /** A further row of the bank line above (one line, several rows). */
    part_of: number | null;
    transaction: AppRow | null;
    /** The pair is confirmed (not just the app's guess). */
    linked: boolean;
    /** What the app row has that the bank doesn't. */
    differences: {
        date?: string;
        /** What the line's row(s) move together, where it isn't the bank's. */
        amount?: number;
        unconfirmed?: true;
        unreconciled?: true;
    };
    /** How to add the row a bank-only line is missing. */
    proposal:
        | { kind: 'fee'; template: null }
        | { kind: 'like'; template: Template | null }
        | null;
};

type WalletInfo = {
    id: number;
    name: string;
    takes_fees: boolean;
    statement_balance: string | null;
    statement_date: string | null;
    app_balance: number | null;
};

type Props = {
    wallets: { id: number; name: string }[];
    wallet: WalletInfo | null;
    period: { from: string; to: string };
    rows: Row[];
};

const STATUS: Record<
    Status,
    { label: string; icon: typeof CircleCheck; className: string }
> = {
    match: {
        label: 'Match',
        icon: CircleCheck,
        className: 'text-emerald-600 dark:text-emerald-400',
    },
    differs: {
        label: 'Differs',
        icon: CircleAlert,
        className: 'text-amber-600 dark:text-amber-400',
    },
    bank_only: {
        label: 'Missing from the app',
        icon: CircleX,
        className: 'text-red-600 dark:text-red-400',
    },
    app_only: {
        label: 'Not at the bank',
        icon: CircleX,
        className: 'text-red-600 dark:text-red-400',
    },
};

const DIFFERS = 'text-amber-700 dark:text-amber-400';

const iso = (d: Date) => format(d, 'yyyy-MM-dd');

function Money({ value, className }: { value: number; className?: string }) {
    return (
        <span
            className={cn(
                'font-medium whitespace-nowrap tabular-nums',
                value > 0 && 'text-emerald-700 dark:text-emerald-400',
                className,
            )}
        >
            {value > 0 ? '+' : '−'}
            {formatAmount(Math.abs(value))}
        </span>
    );
}

/** What the app has (or would add) for a row, in words. */
function AppText({ row, size }: { row: Row; size: number }) {
    const t = row.transaction;
    if (t) {
        const d = row.differences;
        const notes = [
            d.unconfirmed ? 'Not confirmed yet' : null,
            d.unreconciled ? 'Paired, but no longer marked reconciled' : null,
            size > 1 && d.amount !== undefined
                ? `The ${size} rows add up to ${formatAmount(d.amount)}`
                : null,
        ].filter(Boolean);
        const note = notes.length > 0 ? notes.join(' · ') : null;
        return (
            <div className="min-w-0 [overflow-wrap:anywhere] whitespace-normal">
                <Link
                    href={`/transactions?edit=${t.id}`}
                    className="relative z-10 hover:underline"
                >
                    {t.description || whoAndWhat(t)}
                </Link>
                {t.description && (
                    <div className="text-muted-foreground text-xs">
                        {whoAndWhat(t)}
                    </div>
                )}
                {note && <div className={cn('text-xs', DIFFERS)}>{note}</div>}
            </div>
        );
    }
    if (row.status !== 'bank_only') return null;
    const p = row.proposal;
    const guess =
        p?.kind === 'fee'
            ? 'a bank fee'
            : p?.template
              ? whoAndWhat(p.template)
              : null;
    return (
        <span className="text-muted-foreground italic">
            {guess ? `Missing — looks like ${guess}` : 'Missing'}
        </span>
    );
}

/**
 * Finance ▸ Bank import: the bank and the app side by side for a period. The left
 * half is the wallet's bank statement (upload Alpha's CSV export), the right half
 * the app's rows, paired line by line in the bank's order: ✓ match, ⚠ the app thinks
 * it's the same but something differs (Accept makes the row like the bank), ✕ only
 * on one side (Add the missing row, or check the row the bank doesn't have). Drag an
 * app row onto a bank line to pair them by hand.
 */
export default function BankImportIndex({
    wallets,
    wallet,
    period,
    rows,
}: Props) {
    const isMobile = useIsMobile();
    const [adding, setAdding] = useState<BankLinePrefill | null>(null);
    const [addKey, setAddKey] = useState(0);
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [dragging, setDragging] = useState<number | null>(null);
    const [dropOn, setDropOn] = useState<number | null>(null);

    const walletId = wallet?.id ?? 0;
    const appOnly = rows
        .filter((r) => r.status === 'app_only')
        .map((r) => r.transaction)
        .filter((t): t is AppRow => t !== null);
    const counts: Record<Status, number> = {
        match: 0,
        differs: 0,
        bank_only: 0,
        app_only: 0,
    };
    // Pairs are counted, not rows: a line with several rows is one.
    rows.forEach((r) => r.part_of === null && counts[r.status]++);

    // The app rows of each bank line (one, or several the bank booked as one).
    const lineRows = new Map<number, AppRow[]>();
    rows.forEach((r) => {
        const id = r.line?.id ?? r.part_of;
        if (id != null && r.transaction) {
            lineRows.set(id, [...(lineRows.get(id) ?? []), r.transaction]);
        }
    });
    const sizeOf = (r: Row) =>
        lineRows.get(r.line?.id ?? r.part_of ?? 0)?.length ?? 0;

    const show = (params: Record<string, string | number>) =>
        router.get(
            '/bank-import',
            { wallet: walletId, from: period.from, to: period.to, ...params },
            { preserveScroll: true, replace: true },
        );

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

    const post = (url: string, data: Record<string, number | number[]> = {}) =>
        router.post(url, data, { preserveScroll: true, preserveState: true });

    const pair = (lineId: number, transactionId: number) =>
        post(`/bank-lines/${lineId}/match`, { transaction_id: transactionId });

    function openAdd(row: Row, withTemplate: boolean, amount?: number) {
        if (!wallet || !row.line) return;
        setAddKey((k) => k + 1);
        setAdding({
            id: row.line.id,
            date: row.line.date,
            amount: amount ?? row.line.amount,
            text: row.line.text,
            wallet_id: wallet.id,
            template:
                withTemplate && row.proposal?.kind === 'like'
                    ? row.proposal.template
                    : null,
        });
    }

    function remove(t: AppRow) {
        if (
            window.confirm(
                `Delete “${t.description || whoAndWhat(t)}” of ${formatDate(t.date)}?`,
            )
        ) {
            router.delete(`/transactions/${t.id}`, { preserveScroll: true });
        }
    }

    // Drag an app row (by its grip) onto a bank line's row to pair them: every
    // cell of a row with a bank line accepts the drop.
    function zone(row: Row, content: ReactNode, className?: string) {
        // A further row of a line stands for its line too.
        const lineId = row.line?.id ?? row.part_of;
        const accepts = lineId != null && dragging !== null;
        return (
            <div
                className={cn('-my-2 py-2', className)}
                onDragOver={
                    accepts
                        ? (e: DragEvent) => {
                              e.preventDefault();
                              setDropOn(lineId);
                          }
                        : undefined
                }
                onDrop={
                    accepts
                        ? (e: DragEvent) => {
                              e.preventDefault();
                              const id = Number(
                                  e.dataTransfer.getData('text/plain'),
                              );
                              setDropOn(null);
                              setDragging(null);
                              if (id && id !== row.transaction?.id) {
                                  pair(lineId, id);
                              }
                          }
                        : undefined
                }
            >
                {content}
            </div>
        );
    }

    function grip(row: Row) {
        const t = row.transaction;
        if (!t || isMobile) return null;
        return (
            <span
                draggable
                onDragStart={(e) => {
                    e.dataTransfer.setData('text/plain', String(t.id));
                    e.dataTransfer.effectAllowed = 'move';
                    setDragging(t.id);
                }}
                onDragEnd={() => {
                    setDragging(null);
                    setDropOn(null);
                }}
                title="Drag onto a bank line to pair it with this row"
                className="text-muted-foreground hover:text-foreground relative z-10 -ml-1 cursor-grab"
            >
                <GripVertical className="size-4" />
            </span>
        );
    }

    function actions(row: Row) {
        const t = row.transaction;
        const line = row.line;
        const size = sizeOf(row);
        const lineIds = (lineRows.get(line?.id ?? 0) ?? []).map((x) => x.id);
        // Several rows that don't add up stay amber until a row is added or taken
        // out — accepting can't fix that (each keeps its own amount).
        const canAccept =
            row.status === 'differs' &&
            line !== null &&
            t !== null &&
            !(row.linked && size > 1 && row.differences.amount !== undefined);
        const owed =
            line && row.linked && row.differences.amount !== undefined
                ? Math.round(
                      (Math.abs(line.amount) - row.differences.amount) * 100,
                  ) / 100
                : 0;
        let primary: ReactNode = null;
        if (canAccept && line) {
            primary = (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        post(`/bank-lines/${line.id}/accept`, {
                            transaction_ids: lineIds,
                        })
                    }
                    title={
                        size > 1
                            ? `Pair these ${size} rows with the bank line, on its day, and mark them reconciled`
                            : "Give the app row the bank's date and amount, and mark it reconciled"
                    }
                >
                    <Check className="size-4" />
                    Accept
                </Button>
            );
        } else if (row.status === 'bank_only' && line) {
            primary =
                row.proposal?.kind === 'fee' ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => post(`/bank-lines/${line.id}/fee`)}
                        title="Add it as a bank fee row"
                    >
                        <Plus className="size-4" />
                        Add fee
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => openAdd(row, true)}
                        title="Open the add form, filled from this line"
                    >
                        <Plus className="size-4" />
                        Add
                    </Button>
                );
        }

        const options = line ? pairOptions(line, appOnly, walletId) : [];

        return (
            <div className="flex justify-end gap-1">
                {primary}
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
                    <DropdownMenuContent align="end" className="w-64">
                        {t && (
                            <DropdownMenuItem asChild>
                                <Link href={`/transactions?edit=${t.id}`}>
                                    Open the app row
                                </Link>
                            </DropdownMenuItem>
                        )}
                        {line && row.status !== 'match' && (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger
                                    disabled={options.length === 0}
                                >
                                    Pair with another row
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent className="w-80">
                                    <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                        Rows not at the bank · joins the line's
                                        rows while they don't add up to it
                                    </DropdownMenuLabel>
                                    {options.map((o) => (
                                        <DropdownMenuItem
                                            key={o.id}
                                            onSelect={() => pair(line.id, o.id)}
                                        >
                                            <span className="min-w-0 flex-1 truncate">
                                                {formatDate(o.date)}{' '}
                                                {o.description || whoAndWhat(o)}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatAmount(o.amount)}
                                            </span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        )}
                        {line && !row.linked && (
                            <DropdownMenuItem
                                onSelect={() => openAdd(row, false)}
                            >
                                Add a new row for this line…
                            </DropdownMenuItem>
                        )}
                        {line && owed > 0 && (
                            <DropdownMenuItem
                                onSelect={() =>
                                    openAdd(
                                        row,
                                        false,
                                        Math.sign(line.amount) * owed,
                                    )
                                }
                            >
                                Add a row for the missing {formatAmount(owed)}…
                            </DropdownMenuItem>
                        )}
                        {line &&
                            !row.linked &&
                            wallet?.takes_fees &&
                            line.amount < 0 &&
                            row.proposal?.kind !== 'fee' && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        post(`/bank-lines/${line.id}/fee`)
                                    }
                                >
                                    Add as a bank fee
                                </DropdownMenuItem>
                            )}
                        {row.linked && t && (line || row.part_of) && (
                            <>
                                <DropdownMenuSeparator />
                                {size > 1 && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            post(
                                                `/bank-lines/${line?.id ?? row.part_of}/unlink`,
                                                { transaction_id: t.id },
                                            )
                                        }
                                    >
                                        Take this row out of the bank line
                                    </DropdownMenuItem>
                                )}
                                {line && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            post(
                                                `/bank-lines/${line.id}/unlink`,
                                            )
                                        }
                                    >
                                        {size > 1
                                            ? 'Unpair all its rows'
                                            : 'Unpair'}
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                        {row.status === 'app_only' && t && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => remove(t)}
                                >
                                    Delete the app row
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        );
    }

    const appDate = (r: Row) =>
        r.transaction ? (
            <span
                className={cn(
                    'whitespace-nowrap',
                    r.differences.date && DIFFERS,
                )}
            >
                {formatDate(r.transaction.date)}
            </span>
        ) : null;

    const appAmount = (r: Row) =>
        r.transaction ? (
            <Money
                value={signedCash(r.transaction, walletId)}
                className={cn(r.differences.amount !== undefined && DIFFERS)}
            />
        ) : null;

    const statusColumn: ColumnDef<Row> = {
        id: 'status',
        accessorFn: (r) => STATUS[r.status].label,
        meta: { filter: { type: 'select' } },
        enableSorting: false,
        header: ({ column }) => <ColumnHeader column={column} title="" />,
        cell: ({ row }) => {
            const s = STATUS[row.original.status];
            const Icon = s.icon;
            return zone(
                row.original,
                <span title={s.label}>
                    <Icon
                        className={cn('size-4', s.className)}
                        aria-label={s.label}
                    />
                </span>,
            );
        },
    };

    const actionsColumn: ColumnDef<Row> = {
        id: 'actions',
        enableSorting: false,
        meta: { align: 'right' },
        header: () => null,
        cell: ({ row }) => zone(row.original, actions(row.original)),
    };

    const desktopColumns: ColumnDef<Row>[] = [
        statusColumn,
        {
            id: 'bank_date',
            accessorFn: (r) => r.line?.date ?? '',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Bank date" />
            ),
            cell: ({ row }) =>
                zone(
                    row.original,
                    row.original.line ? formatDate(row.original.line.date) : '',
                    'whitespace-nowrap',
                ),
        },
        {
            id: 'bank_text',
            accessorFn: (r) => r.line?.text ?? '',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Bank text" />
            ),
            cell: ({ row }) =>
                zone(
                    row.original,
                    row.original.part_of !== null ? (
                        <span className="text-muted-foreground text-xs">
                            ↳ same bank line
                        </span>
                    ) : (
                        row.original.line?.text
                    ),
                    'break-words whitespace-normal',
                ),
        },
        {
            id: 'bank_amount',
            accessorFn: (r) => r.line?.amount ?? null,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Bank" align="right" />
            ),
            cell: ({ row }) =>
                zone(
                    row.original,
                    row.original.line && (
                        <Money value={row.original.line.amount} />
                    ),
                ),
        },
        {
            id: 'app_date',
            accessorFn: (r) => r.transaction?.date ?? '',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="App date" />
            ),
            cell: ({ row }) =>
                zone(
                    row.original,
                    <div className="flex items-center gap-1.5">
                        {grip(row.original)}
                        {appDate(row.original)}
                    </div>,
                    'border-l pl-3',
                ),
        },
        {
            id: 'app_text',
            accessorFn: (r) =>
                r.transaction
                    ? `${r.transaction.description} ${whoAndWhat(r.transaction)}`
                    : '',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="In the app" />
            ),
            cell: ({ row }) =>
                zone(
                    row.original,
                    <AppText row={row.original} size={sizeOf(row.original)} />,
                ),
        },
        {
            id: 'app_amount',
            accessorFn: (r) =>
                r.transaction ? signedCash(r.transaction, walletId) : null,
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="App" align="right" />
            ),
            cell: ({ row }) => zone(row.original, appAmount(row.original)),
        },
        actionsColumn,
    ];

    // On a phone each side is one stacked cell (date, text, amount), the row's
    // buttons under the app side, and no selection — so nothing is pushed off-screen.
    const mobileColumns: ColumnDef<Row>[] = [
        statusColumn,
        {
            id: 'bank',
            accessorFn: (r) => r.line?.text ?? '',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Bank" />
            ),
            cell: ({ row }) => {
                const l = row.original.line;
                if (row.original.part_of !== null) {
                    return (
                        <span className="text-muted-foreground text-xs">
                            ↳ same bank line
                        </span>
                    );
                }
                return l ? (
                    <div className="space-y-0.5 [overflow-wrap:anywhere] whitespace-normal">
                        <div className="text-muted-foreground text-xs">
                            {formatDate(l.date)}
                        </div>
                        <div>{l.text}</div>
                        <Money value={l.amount} />
                    </div>
                ) : null;
            },
        },
        {
            id: 'app',
            accessorFn: (r) => r.transaction?.description ?? '',
            header: ({ column }) => (
                <ColumnHeader column={column} title="In the app" />
            ),
            cell: ({ row }) => (
                <div className="space-y-0.5 [overflow-wrap:anywhere] whitespace-normal">
                    <div className="text-xs">{appDate(row.original)}</div>
                    <AppText row={row.original} size={sizeOf(row.original)} />
                    <div>{appAmount(row.original)}</div>
                    <div className="pt-1 [&>div]:justify-start">
                        {actions(row.original)}
                    </div>
                </div>
            ),
        },
    ];

    const balanceDiff =
        wallet?.statement_balance != null && wallet.app_balance != null
            ? Math.round(
                  (Number(wallet.statement_balance) - wallet.app_balance) * 100,
              ) / 100
            : null;

    const now = new Date();
    const presets = [
        {
            label: 'This month',
            from: iso(startOfMonth(now)),
            to: iso(endOfMonth(now)),
        },
        {
            label: 'Last month',
            from: iso(startOfMonth(subMonths(now, 1))),
            to: iso(endOfMonth(subMonths(now, 1))),
        },
    ];

    return (
        <>
            <Head title="Bank import" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={isMobile ? mobileColumns : desktopColumns}
                    data={rows}
                    title="Bank import"
                    stateKey={`bank-import-${isMobile ? 'm' : 'd'}`}
                    searchPlaceholder="Search…"
                    pageSize={200}
                    emptyMessage={
                        wallet?.statement_date
                            ? 'Nothing at the bank or in the app in these dates.'
                            : 'No statement uploaded yet — upload the bank’s CSV export.'
                    }
                    rowClassName={(r) =>
                        dropOn !== null && (r.line?.id ?? r.part_of) === dropOn
                            ? 'bg-primary/10 outline-primary outline-2 -outline-offset-2'
                            : undefined
                    }
                    enableSelection={!isMobile}
                    getRowId={(r) => r.key}
                    renderBulkActions={(selected, clear) => {
                        const ids = selected
                            .filter(
                                (r) =>
                                    r.line !== null &&
                                    !r.linked &&
                                    (r.status === 'differs' ||
                                        r.proposal?.kind === 'fee' ||
                                        r.proposal?.template != null),
                            )
                            .map((r) => r.line?.id ?? 0);
                        return (
                            <Button
                                size="sm"
                                disabled={ids.length === 0}
                                title="Accept the app's pairs and add the missing rows as suggested"
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
                                Accept {ids.length}{' '}
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
                        <div className="flex flex-col gap-2 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="w-40">
                                    <DateField
                                        value={period.from}
                                        onChange={(v) => v && show({ from: v })}
                                    />
                                </div>
                                <div className="w-40">
                                    <DateField
                                        value={period.to}
                                        onChange={(v) => v && show({ to: v })}
                                    />
                                </div>
                                {presets.map((p) => {
                                    const on =
                                        period.from === p.from &&
                                        period.to === p.to;
                                    return (
                                        <Button
                                            key={p.label}
                                            variant="outline"
                                            size="sm"
                                            aria-pressed={on}
                                            className={cn(
                                                on &&
                                                    'text-primary border-primary',
                                            )}
                                            onClick={() =>
                                                show({ from: p.from, to: p.to })
                                            }
                                        >
                                            {p.label}
                                        </Button>
                                    );
                                })}
                            </div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                {(Object.keys(STATUS) as Status[]).map((s) => {
                                    const Icon = STATUS[s].icon;
                                    return (
                                        <span
                                            key={s}
                                            className="inline-flex items-center gap-1.5"
                                        >
                                            <Icon
                                                className={cn(
                                                    'size-4',
                                                    STATUS[s].className,
                                                )}
                                            />
                                            {counts[s]} {STATUS[s].label}
                                        </span>
                                    );
                                })}
                                {wallet?.statement_date &&
                                    balanceDiff !== null && (
                                        <span
                                            className={cn(
                                                'sm:ml-auto',
                                                balanceDiff === 0
                                                    ? 'text-emerald-700 dark:text-emerald-400'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            Balance on{' '}
                                            {formatDate(wallet.statement_date)}:
                                            bank{' '}
                                            {formatAmount(
                                                Number(
                                                    wallet.statement_balance,
                                                ),
                                            )}{' '}
                                            · app{' '}
                                            {formatAmount(
                                                wallet.app_balance ?? 0,
                                            )}
                                            {balanceDiff !== 0 &&
                                                ` · off by ${formatAmount(Math.abs(balanceDiff))}`}
                                        </span>
                                    )}
                            </div>
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

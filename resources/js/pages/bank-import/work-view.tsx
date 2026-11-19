import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    Check,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleX,
    Ellipsis,
    GripVertical,
    Plus,
    Undo2,
} from 'lucide-react';
import { type DragEvent, type ReactNode, useState } from 'react';
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
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    type AppRow,
    nextMonth,
    pairOptions,
    signedCash,
    stillNeeded,
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

type Status =
    | 'match'
    | 'differs'
    | 'bank_only'
    | 'app_only'
    | 'decided'
    | 'partial';

/** An open planned item a line may pay (in full or in part). */
export type PlanOption = {
    key: string;
    kind: string;
    id: number | null;
    date: string;
    description: string;
    entity: string | null;
    type: string;
    /** What is still open of it (after this draft's other decisions). */
    open: number;
    total: number;
};

/** One piece of a line's draft decision, in words. */
type Piece = { t: string; text: string; amount: number; rest: string | null };

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
    /** How to add the row a bank-only line is missing, or the item it pays. */
    proposal:
        | { kind: 'fee'; template: null; plan: null }
        | { kind: 'like'; template: Template | null; plan: null }
        | { kind: 'plan'; template: null; plan: PlanOption | null }
        | null;
    /** The draft's decision for the line (an open statement). */
    decision: Piece[] | null;
    /** Planned items the line could pay. */
    plan_options: PlanOption[];
};

/** The statement being worked on (or looked at, once concluded). */
export type Work = {
    id: number;
    open: boolean;
    from: string;
    to: string;
    /** What Undo would take back. */
    last_step: string | null;
    closing_balance: number | null;
    /** The app's checked balance on the statement's last day, as the books stand. */
    app_balance: number;
    wallet: { id: number; name: string; takes_fees: boolean };
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
    decided: {
        label: 'Decided',
        icon: CircleCheck,
        className: 'text-sky-600 dark:text-sky-400',
    },
    partial: {
        label: 'Part decided',
        icon: CircleDashed,
        className: 'text-amber-600 dark:text-amber-400',
    },
};

const DIFFERS = 'text-amber-700 dark:text-amber-400';

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
    if (row.decision) {
        const covered = row.decision.reduce((s, p) => s + p.amount, 0);
        const left = row.line ? stillNeeded(row.line.amount, covered) : 0;
        return (
            <div className="min-w-0 space-y-0.5 [overflow-wrap:anywhere] whitespace-normal">
                {row.decision.map((p, i) => (
                    <div key={i}>
                        {p.text}
                        <span className="text-muted-foreground tabular-nums">
                            {' · '}
                            {formatAmount(p.amount)}
                            {p.rest && ` · rest due ${formatDate(p.rest)}`}
                        </span>
                    </div>
                ))}
                {left > 0 && (
                    <div className={cn('text-xs', DIFFERS)}>
                        {formatAmount(left)} still to decide
                    </div>
                )}
            </div>
        );
    }
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
            : p?.kind === 'plan' && p.plan
              ? `the planned ${p.plan.description || p.plan.entity || 'item'} of ${formatDate(p.plan.date)}`
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
 * Bank import ▸ working on a statement: the bank and the app side by side for its
 * dates. The left half is the statement, the right half the app's rows, paired line
 * by line in the bank's order: ✓ match (done before), ⚠ the app thinks it's the same
 * but something differs, ✕ only on one side, and — on an open statement — each
 * line's **draft decision** (a row, a planned item, a new row, the bank fee). Every
 * click is a saved step (Undo takes it back); nothing changes in the books until
 * **Conclude**. Drag an app row onto a bank line to pair them by hand.
 */
export function WorkView({
    statement,
    heading,
}: {
    statement: Work;
    heading: ReactNode;
}) {
    const rows = statement.rows;
    const wallet = statement.wallet;
    const editable = statement.open;
    const base = `/bank-statements/${statement.id}`;
    const isMobile = useIsMobile();
    const [resting, setResting] = useState<{
        lineId: number;
        option: PlanOption;
        amount: number;
        rest: string;
    } | null>(null);
    const [discrepancy, setDiscrepancy] = useState<number | null>(null);
    const [adding, setAdding] = useState<BankLinePrefill | null>(null);
    const [addKey, setAddKey] = useState(0);
    const [dragging, setDragging] = useState<number | null>(null);
    const [dropOn, setDropOn] = useState<number | null>(null);

    const walletId = wallet.id;
    const appOnly = rows
        .filter((r) => r.status === 'app_only')
        .map((r) => r.transaction)
        .filter((t): t is AppRow => t !== null);
    const counts: Record<Status, number> = {
        match: 0,
        differs: 0,
        bank_only: 0,
        app_only: 0,
        decided: 0,
        partial: 0,
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

    const post = (url: string, data: Record<string, unknown> = {}) =>
        router.post(url, data as never, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Not saved.'),
        });

    const piece = (lineId: number, data: Record<string, unknown>) =>
        post(`${base}/pieces`, { line_id: lineId, ...data });

    const pair = (lineId: number, transactionId: number) =>
        piece(lineId, { t: 'row', id: transactionId });

    // Paying a planned item: in full when it fits; a part asks when the rest is due.
    function payPlan(row: Row, option: PlanOption) {
        if (!row.line) return;
        const covered = (row.decision ?? []).reduce((s, p) => s + p.amount, 0);
        const left =
            row.status === 'decided'
                ? Math.abs(row.line.amount)
                : stillNeeded(row.line.amount, covered);
        const amount = Math.min(left, option.open);
        if (option.open - amount >= 0.005) {
            setResting({
                lineId: row.line.id,
                option,
                amount,
                rest: nextMonth(
                    option.date > row.line.date ? option.date : row.line.date,
                ),
            });
            return;
        }
        piece(row.line.id, { t: 'plan', key: option.key, amount });
    }

    function conclude(acceptDifference = false) {
        router.post(
            `${base}/conclude`,
            { accept_difference: acceptDifference },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.difference !== undefined) {
                        setDiscrepancy(Number(errors.difference));
                    } else {
                        toast.error(
                            Object.values(errors)[0] ?? 'Not concluded.',
                        );
                    }
                },
            },
        );
    }

    function openAdd(row: Row, withTemplate: boolean, amount?: number) {
        if (!row.line) return;
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
        const accepts = editable && lineId != null && dragging !== null;
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
        if (!t || isMobile || !editable || row.linked) return null;
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
        if (!editable) return null;
        const t = row.transaction;
        const line = row.line;
        const decided = row.status === 'decided' || row.status === 'partial';
        // A suggestion to accept: the app's guessed row(s), a planned item, the fee.
        const canAccept =
            line !== null &&
            !row.linked &&
            !decided &&
            (row.status === 'differs' ||
                row.proposal?.kind === 'fee' ||
                row.proposal?.kind === 'plan');
        const covered = (row.decision ?? []).reduce((s, p) => s + p.amount, 0);
        const left = line ? stillNeeded(line.amount, covered) : 0;

        let primary: ReactNode = null;
        if (decided && line) {
            primary = (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        router.delete(`${base}/lines/${line.id}`, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                    title="Take this line's decision back"
                >
                    Clear
                </Button>
            );
        } else if (canAccept && line) {
            const plan =
                row.proposal?.kind === 'plan' ? row.proposal.plan : null;
            primary = (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        plan
                            ? payPlan(row, plan)
                            : post(`${base}/apply`, { ids: [line.id] })
                    }
                    title="Decide the line as suggested (applied when the statement is concluded)"
                >
                    <Check className="size-4" />
                    Accept
                </Button>
            );
        } else if (row.status === 'bank_only' && line) {
            primary = (
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

        const options =
            line && !row.linked ? pairOptions(line, appOnly, walletId) : [];
        const plans = row.plan_options;

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
                        {line && !row.linked && (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger
                                    disabled={plans.length === 0}
                                >
                                    Pays a planned item
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent className="w-96">
                                    <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                        Open planned items · a part leaves the
                                        rest due on a new date
                                    </DropdownMenuLabel>
                                    {plans.map((o) => (
                                        <DropdownMenuItem
                                            key={o.key}
                                            onSelect={() => payPlan(row, o)}
                                        >
                                            <span className="min-w-0 flex-1 truncate">
                                                {formatDate(o.date)}{' '}
                                                {o.description ||
                                                    o.entity ||
                                                    'Planned'}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatAmount(o.open)}
                                            </span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        )}
                        {line && !row.linked && (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger
                                    disabled={options.length === 0}
                                >
                                    Pair with a row
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent className="w-80">
                                    <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                        Rows not at the bank · joins the line's
                                        decision while it doesn't add up yet
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
                                onSelect={() =>
                                    openAdd(
                                        row,
                                        false,
                                        row.status === 'partial'
                                            ? Math.sign(line.amount) * left
                                            : undefined,
                                    )
                                }
                            >
                                {row.status === 'partial'
                                    ? `Add a row for the missing ${formatAmount(left)}…`
                                    : 'Add a new row for this line…'}
                            </DropdownMenuItem>
                        )}
                        {line &&
                            !row.linked &&
                            row.proposal?.kind === 'like' &&
                            row.proposal.template && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        post(`${base}/apply`, {
                                            ids: [line.id],
                                        })
                                    }
                                >
                                    Add like {whoAndWhat(row.proposal.template)}
                                </DropdownMenuItem>
                            )}
                        {line &&
                            !row.linked &&
                            wallet.takes_fees &&
                            line.amount < 0 && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        piece(line.id, { t: 'fee' })
                                    }
                                >
                                    Add as a bank fee
                                </DropdownMenuItem>
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
        r.decision && r.line ? (
            <Money
                value={
                    Math.sign(r.line.amount) *
                    r.decision.reduce((sum, p) => sum + p.amount, 0)
                }
                className={cn(r.status === 'partial' && DIFFERS)}
            />
        ) : r.transaction ? (
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
        statement.closing_balance !== null
            ? Math.round(
                  (statement.closing_balance - statement.app_balance) * 100,
              ) / 100
            : null;
    const toDecide = counts.bank_only + counts.differs + counts.partial;

    return (
        <>
            <DataTable
                columns={isMobile ? mobileColumns : desktopColumns}
                data={rows}
                title="Bank import"
                heading={heading}
                stateKey={`bank-import-${isMobile ? 'm' : 'd'}`}
                searchPlaceholder="Search…"
                pageSize={200}
                emptyMessage="No lines in this statement."
                rowClassName={(r) =>
                    dropOn !== null && (r.line?.id ?? r.part_of) === dropOn
                        ? 'bg-primary/10 outline-primary outline-2 -outline-offset-2'
                        : undefined
                }
                enableSelection={!isMobile && editable}
                getRowId={(r) => r.key}
                renderBulkActions={(selected, clear) => {
                    const ids = selected
                        .filter(
                            (r) =>
                                r.line !== null &&
                                !r.linked &&
                                !r.decision &&
                                (r.status === 'differs' ||
                                    r.proposal?.kind === 'fee' ||
                                    r.proposal?.kind === 'plan' ||
                                    r.proposal?.template != null),
                        )
                        .map((r) => r.line?.id ?? 0);
                    return (
                        <Button
                            size="sm"
                            disabled={ids.length === 0}
                            title="Decide the chosen lines as suggested"
                            onClick={() =>
                                router.post(
                                    `${base}/apply`,
                                    { ids },
                                    { preserveScroll: true, onSuccess: clear },
                                )
                            }
                        >
                            <Check className="size-4" />
                            Accept {ids.length}{' '}
                            {ids.length === 1 ? 'suggestion' : 'suggestions'}
                        </Button>
                    );
                }}
                toolbar={
                    editable ? (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={statement.last_step === null}
                                title={
                                    statement.last_step
                                        ? `Undo: ${statement.last_step}`
                                        : 'Nothing to undo'
                                }
                                onClick={() => post(`${base}/undo`)}
                            >
                                <Undo2 className="size-4" />
                                Undo
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Statement choices"
                                    >
                                        <Ellipsis className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => {
                                            if (
                                                window.confirm(
                                                    'Discard this statement and its decisions? Its lines go too (upload it again to start over).',
                                                )
                                            ) {
                                                router.delete(base);
                                            }
                                        }}
                                    >
                                        Discard the statement
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </>
                    ) : undefined
                }
                action={
                    editable ? (
                        <Button
                            onClick={() => conclude()}
                            title={
                                toDecide > 0
                                    ? `${toDecide} line(s) still to decide`
                                    : 'Apply every decision and mark the books complete up to the statement’s last day'
                            }
                        >
                            <Check className="size-4" />
                            Conclude
                        </Button>
                    ) : undefined
                }
                controls={
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span className="font-medium">
                            {wallet.name} · {formatDate(statement.from)}–
                            {formatDate(statement.to)}
                            {!editable && ' · concluded'}
                        </span>
                        {(Object.keys(STATUS) as Status[])
                            .filter((s) => counts[s] > 0)
                            .map((s) => {
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
                        {balanceDiff !== null && (
                            <span className="text-muted-foreground sm:ml-auto">
                                Closing balance: bank{' '}
                                {formatAmount(statement.closing_balance ?? 0)} ·
                                app now {formatAmount(statement.app_balance)}
                            </span>
                        )}
                    </div>
                }
            />

            <TransactionFormDialog
                key={`bank-add-${addKey}`}
                open={adding !== null}
                onOpenChange={(open) => !open && setAdding(null)}
                bankLine={adding}
            />

            <Dialog
                open={resting !== null}
                onOpenChange={(open) => !open && setResting(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Paid in part</DialogTitle>
                        <DialogDescription>
                            {resting &&
                                `${formatAmount(resting.amount)} of the ${formatAmount(resting.option.open)} open is paid now. When is the remaining ${formatAmount(resting.option.open - resting.amount)} expected?`}
                        </DialogDescription>
                    </DialogHeader>
                    {resting && (
                        <DateField
                            value={resting.rest}
                            showCalendar={false}
                            onChange={(v) =>
                                setResting({ ...resting, rest: v })
                            }
                        />
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setResting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!resting?.rest}
                            onClick={() => {
                                if (!resting) return;
                                piece(resting.lineId, {
                                    t: 'plan',
                                    key: resting.option.key,
                                    amount: resting.amount,
                                    rest: resting.rest,
                                });
                                setResting(null);
                            }}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={discrepancy !== null}
                onOpenChange={(open) => !open && setDiscrepancy(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>The balance is off</DialogTitle>
                        <DialogDescription>
                            After these decisions the app's balance on{' '}
                            {formatDate(statement.to)} would differ from the
                            bank's{' '}
                            {formatAmount(statement.closing_balance ?? 0)} by{' '}
                            {formatAmount(Math.abs(discrepancy ?? 0))}.
                            Something is missing or doubled — conclude anyway?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDiscrepancy(null)}
                        >
                            Go back
                        </Button>
                        <Button
                            onClick={() => {
                                setDiscrepancy(null);
                                conclude(true);
                            }}
                        >
                            Conclude anyway
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

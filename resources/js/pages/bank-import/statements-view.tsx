import { router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CalendarCheck, Upload } from 'lucide-react';
import { type ChangeEvent, type ReactNode, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';

export type WalletRow = {
    id: number;
    name: string;
    concluded_until: string | null;
    has_open: boolean;
};

export type HistoryRow = {
    key: string;
    kind: 'statement' | 'hand';
    id: number;
    wallet: string;
    from: string | null;
    to: string;
    status: 'open' | 'concluded';
    lines: number | null;
    by: string | null;
    at: string;
};

const columns: ColumnDef<HistoryRow>[] = [
    {
        id: 'wallet',
        accessorFn: (r) => r.wallet,
        meta: { filter: { type: 'select' } },
        header: ({ column }) => <ColumnHeader column={column} title="Wallet" />,
    },
    {
        id: 'dates',
        accessorFn: (r) => r.to,
        meta: { filter: { type: 'date' } },
        header: ({ column }) => <ColumnHeader column={column} title="Covers" />,
        cell: ({ row }) =>
            row.original.from
                ? `${formatDate(row.original.from)}–${formatDate(row.original.to)}`
                : `up to ${formatDate(row.original.to)} (by hand)`,
    },
    {
        id: 'status',
        accessorFn: (r) => (r.status === 'open' ? 'Open' : 'Concluded'),
        meta: { filter: { type: 'select' } },
        header: ({ column }) => <ColumnHeader column={column} title="Status" />,
        cell: ({ row }) =>
            row.original.status === 'open' ? (
                <span className="font-medium text-amber-700 dark:text-amber-400">
                    Open — being worked on
                </span>
            ) : (
                'Concluded'
            ),
    },
    {
        id: 'lines',
        accessorFn: (r) => r.lines ?? 0,
        meta: { align: 'right' },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Lines" align="right" />
        ),
        cell: ({ row }) => row.original.lines ?? '—',
    },
    {
        id: 'by',
        accessorFn: (r) => r.by ?? '',
        header: ({ column }) => <ColumnHeader column={column} title="By" />,
        cell: ({ row }) => row.original.by ?? '—',
    },
    {
        id: 'at',
        accessorFn: (r) => r.at,
        header: ({ column }) => <ColumnHeader column={column} title="On" />,
        cell: ({ row }) => formatDate(row.original.at),
    },
];

/**
 * Bank import ▸ Statements: every upload and every wallet concluded by hand, newest
 * first — where the books stand ("last concluded up to …"). Upload a statement for
 * a wallet (one open at a time per wallet); a wallet without statements (cash, the
 * others) is concluded by hand up to a date. A statement opens its lines.
 */
export function StatementsView({
    history,
    wallets,
    heading,
}: {
    history: HistoryRow[];
    wallets: WalletRow[];
    heading: ReactNode;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [walletId, setWalletId] = useState(
        String(
            (
                wallets.find((w) => w.concluded_until && !w.has_open) ??
                wallets[0]
            )?.id ?? '',
        ),
    );
    const [uploading, setUploading] = useState(false);
    const [concluding, setConcluding] = useState<WalletRow | null>(null);

    function upload(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !walletId) return;
        router.post(
            '/bank-import',
            { wallet_id: walletId, file },
            {
                forceFormData: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onError: (errors) =>
                    toast.error(Object.values(errors)[0] ?? 'Not uploaded.'),
            },
        );
    }

    return (
        <>
            <DataTable
                columns={columns}
                data={history}
                title="Statements"
                heading={heading}
                emptyMessage="Nothing uploaded or concluded yet."
                onRowClick={(r) =>
                    r.kind === 'statement' &&
                    router.get('/bank-import', { tab: 'work', statement: r.id })
                }
                toolbar={
                    <Select value={walletId} onValueChange={setWalletId}>
                        <SelectTrigger className="w-40" aria-label="Wallet">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {wallets.map((w) => (
                                <SelectItem key={w.id} value={String(w.id)}>
                                    {w.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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
                            disabled={!walletId || uploading}
                            title="Upload a statement (the bank's CSV) for the chosen wallet"
                        >
                            <Upload className="size-4" />
                            Upload
                        </Button>
                    </>
                }
                controls={
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        <span className="text-muted-foreground">
                            Complete up to:
                        </span>
                        {wallets.map((w) => (
                            <span
                                key={w.id}
                                className="inline-flex items-center gap-1"
                            >
                                <span className="font-medium">{w.name}</span>{' '}
                                {w.concluded_until
                                    ? formatDate(w.concluded_until)
                                    : 'not yet'}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    title={`Conclude ${w.name} up to a date (books checked by hand)`}
                                    aria-label={`Conclude ${w.name}`}
                                    onClick={() => setConcluding(w)}
                                >
                                    <CalendarCheck className="size-4" />
                                </Button>
                            </span>
                        ))}
                    </div>
                }
            />
            {concluding && (
                <ConcludeWalletDialog
                    wallet={concluding}
                    onClose={() => setConcluding(null)}
                />
            )}
        </>
    );
}

function ConcludeWalletDialog({
    wallet,
    onClose,
}: {
    wallet: WalletRow;
    onClose: () => void;
}) {
    const form = useForm({ until: new Date().toISOString().slice(0, 10) });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form
                    className="contents"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/wallets/${wallet.id}/conclude`, {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Conclude {wallet.name}</DialogTitle>
                        <DialogDescription>
                            Its books are complete up to this date: planned
                            items dated up to it that nothing paid show under
                            Not matched yet.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="conclude-until">Complete up to</Label>
                        <DateField
                            id="conclude-until"
                            value={form.data.until}
                            showCalendar={false}
                            required
                            onChange={(v) => form.setData('until', v)}
                        />
                        <InputError message={form.errors.until} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Conclude
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

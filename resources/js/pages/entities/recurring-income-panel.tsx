import { router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { AmountInput } from '@/components/amount-input';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { normalizeAmount } from '@/lib/amounts';
import { formatAmount, formatDate } from '@/lib/format';

export type RecurringIncomeRow = {
    id: number;
    entity_id: number;
    wallet_id: number;
    vat_rate_id: number | null;
    category_id: number | null;
    description: string;
    day_of_month: number;
    net: number;
    start_date: string;
    end_date: string | null;
};

/**
 * A customer's recurring income (rare): an amount every month on a day, over a date
 * range. It creates the customer's payment lines; changing it rebuilds only the
 * lines nobody touched (edited, issued or paid lines stay as they are).
 */
export function RecurringIncomePanel({
    entityId,
    rows,
    heading,
}: {
    entityId: number;
    rows: RecurringIncomeRow[];
    /** Shown in the title's place (the customer's tab bar). */
    heading?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const [editing, setEditing] = useState<RecurringIncomeRow | null>(null);

    function openForm(row: RecurringIncomeRow | null) {
        setEditing(row);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    const columns: ColumnDef<RecurringIncomeRow>[] = [
        {
            id: 'description',
            accessorFn: (r) => r.description,
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'day',
            accessorFn: (r) => r.day_of_month,
            header: ({ column }) => (
                <ColumnHeader column={column} title="Day" align="right" />
            ),
            meta: { align: 'right' },
        },
        {
            id: 'from',
            accessorFn: (r) => r.start_date,
            header: ({ column }) => (
                <ColumnHeader column={column} title="From" />
            ),
            cell: ({ row }) => formatDate(row.original.start_date),
        },
        {
            id: 'to',
            accessorFn: (r) => r.end_date ?? '',
            header: ({ column }) => <ColumnHeader column={column} title="To" />,
            cell: ({ row }) =>
                row.original.end_date
                    ? formatDate(row.original.end_date)
                    : 'Ongoing',
        },
        {
            id: 'net',
            accessorFn: (r) => r.net,
            meta: { align: 'right' },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row }) => (
                <div className="relative z-10 flex justify-end">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Delete recurring income"
                        title="Delete recurring income (its untouched payment lines go too)"
                        onClick={() => {
                            if (confirm('Delete this recurring income?')) {
                                router.delete(
                                    `/recurring-incomes/${row.original.id}`,
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <DataTable
                columns={columns}
                data={rows}
                title="Recurring income"
                heading={heading}
                emptyMessage="No recurring income."
                onRowClick={openForm}
                action={
                    <Button
                        size="icon"
                        onClick={() => openForm(null)}
                        aria-label="Add recurring income"
                        title="Add recurring income"
                    >
                        <Plus className="size-4" />
                    </Button>
                }
            />
            <RecurringIncomeDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
                entityId={entityId}
                editing={editing}
            />
        </>
    );
}

function RecurringIncomeDialog({
    open,
    onOpenChange,
    entityId,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entityId: number;
    editing: RecurringIncomeRow | null;
}) {
    const { wallets, vatRates, categories } = useFinanceLookups();
    const defaultVat =
        vatRates.find((r) => Number(r.rate) === 24) ?? vatRates[0];
    const defaultWallet =
        wallets.find((w) => w.concluded_until) ?? wallets[0] ?? null;
    const incomeCategories = categories.filter((c) => c.type === 'income');
    const form = useForm({
        entity_id: String(entityId),
        description: editing?.description ?? '',
        net: editing ? String(editing.net) : '',
        vat_rate_id: editing
            ? editing.vat_rate_id
                ? String(editing.vat_rate_id)
                : 'none'
            : defaultVat
              ? String(defaultVat.id)
              : 'none',
        day_of_month: String(editing?.day_of_month ?? 1),
        start_date:
            editing?.start_date ?? new Date().toISOString().slice(0, 10),
        end_date: editing?.end_date ?? '',
        wallet_id: editing
            ? String(editing.wallet_id)
            : defaultWallet
              ? String(defaultWallet.id)
              : '',
        category_id: editing?.category_id
            ? String(editing.category_id)
            : incomeCategories[0]
              ? String(incomeCategories[0].id)
              : '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            net: normalizeAmount(d.net),
            vat_rate_id: d.vat_rate_id === 'none' ? null : d.vat_rate_id,
            end_date: d.end_date || null,
            category_id: d.category_id || null,
        }));
        const done = {
            onSuccess: () => onOpenChange(false),
            preserveScroll: true,
        };
        if (editing) form.patch(`/recurring-incomes/${editing.id}`, done);
        else form.post('/recurring-incomes', done);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="contents">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Edit recurring income'
                                : 'Add recurring income'}
                        </DialogTitle>
                        <DialogDescription>
                            Creates a payment line every month on the day.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="ri-description">Description</Label>
                            <Input
                                id="ri-description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="e.g. Maintenance"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-net">Net</Label>
                            <AmountInput
                                id="ri-net"
                                value={form.data.net}
                                onChange={(v) => form.setData('net', v)}
                                required
                            />
                            <InputError message={form.errors.net} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-vat">VAT</Label>
                            <Select
                                value={form.data.vat_rate_id}
                                onValueChange={(v) =>
                                    form.setData('vat_rate_id', v)
                                }
                            >
                                <SelectTrigger id="ri-vat">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No VAT (0%)
                                    </SelectItem>
                                    {vatRates.map((r) => (
                                        <SelectItem
                                            key={r.id}
                                            value={String(r.id)}
                                        >
                                            {r.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-day">Day of month</Label>
                            <Input
                                id="ri-day"
                                type="number"
                                min={1}
                                max={31}
                                value={form.data.day_of_month}
                                onChange={(e) =>
                                    form.setData('day_of_month', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.day_of_month} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-wallet">Wallet</Label>
                            <Select
                                value={form.data.wallet_id}
                                onValueChange={(v) =>
                                    form.setData('wallet_id', v)
                                }
                            >
                                <SelectTrigger id="ri-wallet">
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
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-from">From</Label>
                            <DateField
                                id="ri-from"
                                value={form.data.start_date}
                                showCalendar={false}
                                required
                                onChange={(iso) =>
                                    form.setData('start_date', iso)
                                }
                            />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ri-to">To (optional)</Label>
                            <DateField
                                id="ri-to"
                                value={form.data.end_date}
                                showCalendar={false}
                                onChange={(iso) =>
                                    form.setData('end_date', iso)
                                }
                            />
                            <InputError message={form.errors.end_date} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing ? 'Save' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

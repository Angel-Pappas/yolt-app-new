import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AmountInput } from '@/components/amount-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatAmount } from '@/lib/format';
import { normalizeAmount } from '@/lib/amounts';

type Wallet = {
    id: number;
    name: string;
    starting_balance: string;
    balance: number;
    fee_entity_id: number | null;
    fee_category_id: number | null;
};

const EMPTY = {
    name: '',
    starting_balance: '0',
    fee_entity_id: '',
    fee_category_id: '',
};

export default function WalletsIndex({ wallets }: { wallets: Wallet[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Wallet | null>(null);

    const { entities, categories } = useFinanceLookups();
    const form = useForm(EMPTY);

    function openCreate() {
        setEditing(null);
        form.setData(EMPTY);
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(wallet: Wallet) {
        setEditing(wallet);
        form.setData({
            name: wallet.name,
            starting_balance: wallet.starting_balance,
            fee_entity_id: wallet.fee_entity_id
                ? String(wallet.fee_entity_id)
                : '',
            fee_category_id: wallet.fee_category_id
                ? String(wallet.fee_category_id)
                : '',
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        // Accept a comma or a dot as the decimal separator; the server wants a dot.
        form.transform((data) => ({
            ...data,
            starting_balance: normalizeAmount(String(data.starting_balance)),
            fee_entity_id: data.fee_entity_id || null,
            fee_category_id: data.fee_category_id || null,
        }));

        const options = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };

        if (editing) {
            form.patch(`/wallets/${editing.id}`, options);
        } else {
            form.post('/wallets', options);
        }
    }

    function destroy(wallet: Wallet) {
        if (confirm(`Delete wallet "${wallet.name}"?`)) {
            router.delete(`/wallets/${wallet.id}`, { preserveScroll: true });
        }
    }

    const columns: ColumnDef<Wallet>[] = [
        {
            accessorKey: 'name',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'starting_balance',
            accessorFn: (row) => Number(row.starting_balance),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title="Starting balance"
                    align="right"
                />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatAmount(row.original.starting_balance)}
                </span>
            ),
        },
        {
            id: 'balance',
            accessorFn: (row) => Number(row.balance),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Balance" align="right" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(row.original.balance)}
                </span>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <div
                    // Above the row's stretched open-in-new-tab link.
                    className="relative z-10 flex justify-end gap-1"
                    onClick={(e) => e.stopPropagation()}
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(row.original)}
                        aria-label={`Edit ${row.original.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label={`Delete ${row.original.name}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Wallets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={wallets}
                    title="Wallets"
                    searchPlaceholder="Search wallets…"
                    emptyMessage="No wallets yet."
                    pageSize={50}
                    // A wallet opens its transactions with the running balance.
                    onRowClick={(w) =>
                        router.visit(`/transactions?balance=${w.id}&all=1`)
                    }
                    rowHref={(w) => `/transactions?balance=${w.id}&all=1`}
                    action={
                        <Button
                            onClick={openCreate}
                            size="icon"
                            aria-label="Add wallet"
                            title="Add wallet"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit wallet' : 'Add wallet'}
                            </DialogTitle>
                            <DialogDescription>
                                A place money is held — a bank account, cash,
                                and so on.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    autoFocus
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="starting_balance">
                                    Starting balance (€)
                                </Label>
                                <AmountInput
                                    id="starting_balance"
                                    value={form.data.starting_balance}
                                    onChange={(v) =>
                                        form.setData('starting_balance', v)
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.starting_balance}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fee_entity_id">
                                    Bank fees go to
                                </Label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <Combobox
                                        id="fee_entity_id"
                                        value={form.data.fee_entity_id}
                                        onChange={(v) =>
                                            form.setData('fee_entity_id', v)
                                        }
                                        options={entities.map((e) => ({
                                            value: String(e.id),
                                            label: e.name,
                                        }))}
                                        placeholder="Entity"
                                        searchPlaceholder="Search entities…"
                                        emptyText="No entities found."
                                        allowNone
                                    />
                                    <Combobox
                                        id="fee_category_id"
                                        value={form.data.fee_category_id}
                                        onChange={(v) =>
                                            form.setData('fee_category_id', v)
                                        }
                                        options={categories
                                            .filter((c) => c.type === 'expense')
                                            .map((c) => ({
                                                value: String(c.id),
                                                label: c.name,
                                            }))}
                                        placeholder="Category"
                                        searchPlaceholder="Search categories…"
                                        emptyText="No categories found."
                                        allowNone
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    For a bank account: lets you add the bank's
                                    fee with a payment, and the Bank import add
                                    fee lines in one click.
                                </p>
                                <InputError
                                    message={
                                        form.errors.fee_entity_id ??
                                        form.errors.fee_category_id
                                    }
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
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
        </>
    );
}

WalletsIndex.layout = {
    breadcrumbs: [{ title: 'Wallets', href: '/wallets' }],
};

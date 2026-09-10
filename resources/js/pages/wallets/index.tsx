import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { formatAmount } from '@/lib/format';

type Wallet = {
    id: number;
    name: string;
    starting_balance: string;
    balance: number;
};

export default function WalletsIndex({ wallets }: { wallets: Wallet[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Wallet | null>(null);

    const form = useForm({ name: '', starting_balance: '0' });

    function openCreate() {
        setEditing(null);
        form.setData({ name: '', starting_balance: '0' });
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(wallet: Wallet) {
        setEditing(wallet);
        form.setData({
            name: wallet.name,
            starting_balance: wallet.starting_balance,
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        // Accept a comma or a dot as the decimal separator; the server wants a dot.
        form.transform((data) => ({
            ...data,
            starting_balance: String(data.starting_balance).replace(',', '.'),
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
                <div className="flex justify-end gap-1">
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
                    action={
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Add wallet
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
                                <Input
                                    id="starting_balance"
                                    inputMode="decimal"
                                    value={form.data.starting_balance}
                                    onChange={(e) =>
                                        form.setData(
                                            'starting_balance',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.starting_balance}
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

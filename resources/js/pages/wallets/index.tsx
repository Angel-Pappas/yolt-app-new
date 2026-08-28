import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
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

type Wallet = {
    id: number;
    name: string;
    starting_balance: string;
};

// Simple money display for now; Greek-style formatting arrives with Transactions.
function formatAmount(value: string): string {
    return `€ ${Number(value).toFixed(2)}`;
}

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

    return (
        <>
            <Head title="Wallets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Wallets</h1>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add wallet
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 text-right font-medium">
                                    Starting balance
                                </th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {wallets.map((wallet) => (
                                <tr key={wallet.id} className="border-t">
                                    <td className="p-3 font-medium">
                                        {wallet.name}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatAmount(wallet.starting_balance)}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(wallet)}
                                                aria-label={`Edit ${wallet.name}`}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => destroy(wallet)}
                                                aria-label={`Delete ${wallet.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {wallets.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No wallets yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
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

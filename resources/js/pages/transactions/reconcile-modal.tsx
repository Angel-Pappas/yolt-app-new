import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Option = { id: number; name: string };

export type ReconcileTarget = {
    id: number;
    type: 'income' | 'expense' | 'transfer';
    date: string;
    net: string;
    wallet_id: number;
    to_wallet_id: number | null;
    is_reconciled: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction: ReconcileTarget;
    wallets: Option[];
};

/**
 * Reconcile a transaction — the reduced edit of just the fields that drift (date,
 * amount, wallet(s)) plus the reconciled flag.
 */
export function ReconcileModal({
    open,
    onOpenChange,
    transaction,
    wallets,
}: Props) {
    const isTransfer = transaction.type === 'transfer';
    const form = useForm({
        date: transaction.date.slice(0, 10),
        net: transaction.net,
        wallet_id: String(transaction.wallet_id),
        to_wallet_id: transaction.to_wallet_id
            ? String(transaction.to_wallet_id)
            : '',
        is_reconciled: true as boolean,
    });

    const sameWallet =
        isTransfer && form.data.wallet_id === form.data.to_wallet_id;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            net: String(data.net).replace(',', '.'),
        }));
        form.post(`/transactions/${transaction.id}/reconcile`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Reconcile transaction</DialogTitle>
                        <DialogDescription>
                            Correct the date, amount, or wallet if they drifted,
                            then mark it reconciled.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="date">Date</Label>
                            <DateField
                                id="date"
                                value={form.data.date}
                                showCalendar={false}
                                required
                                onChange={(iso) => form.setData('date', iso)}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="net">Amount</Label>
                            <Input
                                id="net"
                                inputMode="decimal"
                                value={form.data.net}
                                onChange={(e) =>
                                    form.setData('net', e.target.value)
                                }
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="wallet_id">
                                {isTransfer ? 'From wallet' : 'Wallet'}
                            </Label>
                            <Select
                                value={form.data.wallet_id}
                                onValueChange={(v) =>
                                    form.setData('wallet_id', v)
                                }
                            >
                                <SelectTrigger id="wallet_id">
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

                        {isTransfer && (
                            <div className="grid gap-2">
                                <Label htmlFor="to_wallet_id">To wallet</Label>
                                <Select
                                    value={form.data.to_wallet_id}
                                    onValueChange={(v) =>
                                        form.setData('to_wallet_id', v)
                                    }
                                >
                                    <SelectTrigger id="to_wallet_id">
                                        <SelectValue placeholder="Select a wallet" />
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
                        )}

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_reconciled"
                                checked={form.data.is_reconciled}
                                onCheckedChange={(v) =>
                                    form.setData('is_reconciled', v === true)
                                }
                            />
                            <Label htmlFor="is_reconciled">
                                Mark as reconciled
                            </Label>
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
                        <Button
                            type="submit"
                            disabled={form.processing || sameWallet}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

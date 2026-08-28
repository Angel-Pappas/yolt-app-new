import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatAmount } from '@/lib/format';

type Option = { id: number; name: string };
type Category = { id: number; name: string; type: string };
type VatRate = { id: number; name: string; rate: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallets: Option[];
    entities: Option[];
    categories: Category[];
    vatRates: VatRate[];
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function parseAmount(value: string): number {
    const n = Number(String(value).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
}

function round2(n: number): number {
    return Math.round(n * 100) / 100;
}

const NONE = 'none';

export function TransactionFormDialog({
    open,
    onOpenChange,
    wallets,
    entities,
    categories,
    vatRates,
}: Props) {
    const form = useForm({
        type: 'expense',
        date: today(),
        invoice_date: today(),
        description: '',
        entity_id: '',
        category_id: '',
        wallet_id: wallets[0] ? String(wallets[0].id) : '',
        net: '',
        vat_rate_id: '',
    });

    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );

    const net = parseAmount(form.data.net);
    const selectedRate = vatRates.find(
        (r) => String(r.id) === form.data.vat_rate_id,
    );
    const vat = selectedRate
        ? round2((net * Number(selectedRate.rate)) / 100)
        : 0;
    const total = round2(net + vat);

    function changeType(value: string) {
        form.setData('type', value);
        form.setData('category_id', '');
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => ({
            type: data.type,
            date: data.date,
            invoice_date: data.invoice_date,
            description: data.description,
            entity_id: data.entity_id || null,
            category_id: data.category_id || null,
            wallet_id: data.wallet_id,
            lines: [
                {
                    net: String(data.net).replace(',', '.'),
                    vat_rate_id: data.vat_rate_id || null,
                },
            ],
        }));

        form.post('/transactions', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Add transaction</DialogTitle>
                        <DialogDescription>
                            Record income or an expense. VAT is calculated from
                            the selected rate.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="type">Type</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={changeType}
                            >
                                <SelectTrigger id="type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="income">
                                        Income
                                    </SelectItem>
                                    <SelectItem value="expense">
                                        Expense
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="date">Date</Label>
                            <Input
                                id="date"
                                type="date"
                                value={form.data.date}
                                onChange={(e) =>
                                    form.setData('date', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="invoice_date">Invoice date</Label>
                            <Input
                                id="invoice_date"
                                type="date"
                                value={form.data.invoice_date}
                                onChange={(e) =>
                                    form.setData('invoice_date', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.invoice_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="entity_id">Entity</Label>
                            <Select
                                value={form.data.entity_id || NONE}
                                onValueChange={(v) =>
                                    form.setData(
                                        'entity_id',
                                        v === NONE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger id="entity_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — None —
                                    </SelectItem>
                                    {entities.map((entity) => (
                                        <SelectItem
                                            key={entity.id}
                                            value={String(entity.id)}
                                        >
                                            {entity.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category_id">Category</Label>
                            <Select
                                value={form.data.category_id || NONE}
                                onValueChange={(v) =>
                                    form.setData(
                                        'category_id',
                                        v === NONE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger id="category_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — None —
                                    </SelectItem>
                                    {availableCategories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="wallet_id">Wallet</Label>
                            <Select
                                value={form.data.wallet_id}
                                onValueChange={(v) =>
                                    form.setData('wallet_id', v)
                                }
                            >
                                <SelectTrigger id="wallet_id">
                                    <SelectValue placeholder="Select a wallet" />
                                </SelectTrigger>
                                <SelectContent>
                                    {wallets.map((wallet) => (
                                        <SelectItem
                                            key={wallet.id}
                                            value={String(wallet.id)}
                                        >
                                            {wallet.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.wallet_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="net">Net amount</Label>
                            <Input
                                id="net"
                                inputMode="decimal"
                                value={form.data.net}
                                onChange={(e) =>
                                    form.setData('net', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={
                                    (
                                        form.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    )['lines.0.net']
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="vat_rate_id">VAT rate</Label>
                            <Select
                                value={form.data.vat_rate_id || NONE}
                                onValueChange={(v) =>
                                    form.setData(
                                        'vat_rate_id',
                                        v === NONE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger id="vat_rate_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — None —
                                    </SelectItem>
                                    {vatRates.map((rate) => (
                                        <SelectItem
                                            key={rate.id}
                                            value={String(rate.id)}
                                        >
                                            {rate.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </div>

                        <div className="bg-muted/50 grid grid-cols-3 gap-2 rounded-lg p-3 text-sm tabular-nums sm:col-span-2">
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Net
                                </div>
                                {formatAmount(net)}
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    VAT
                                </div>
                                {formatAmount(vat)}
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Total
                                </div>
                                <span className="font-medium">
                                    {formatAmount(total)}
                                </span>
                            </div>
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
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

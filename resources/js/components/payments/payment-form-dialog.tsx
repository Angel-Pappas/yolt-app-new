import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { AmountInput } from '@/components/amount-input';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
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
import { type AmountMode, normalizeAmount, sumLines } from '@/lib/amounts';
import { formatAmount } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type PaymentRow } from './types';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

/**
 * Add or edit a customer payment line: payment date, invoice date, the amount as
 * net or total at one VAT rate (24% unless chosen otherwise; 0% for a sale abroad),
 * the wallet it lands in, its category and a description. The invoice date follows
 * the payment date until the invoice is issued (or part of the line is paid) — then
 * it stays put, still editable by hand.
 */
export function PaymentFormDialog({
    open,
    onOpenChange,
    editing = null,
    entityId = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing?: PaymentRow | null;
    /** Adding from a customer's page: that customer, fixed. */
    entityId?: number | null;
}) {
    const { entities, wallets, categories, vatRates, withheldRates } =
        useFinanceLookups();
    const customers = entities.filter((e) => e.type === 'customer');
    const incomeCategories = categories.filter((c) => c.type === 'income');
    const defaultVat =
        vatRates.find((r) => Number(r.rate) === 24) ?? vatRates[0];
    // The wallet a customer usually pays into: the one with bank statements.
    const defaultWallet =
        wallets.find((w) => w.concluded_until) ?? wallets[0] ?? null;

    const form = useForm({
        entity_id: editing
            ? String(editing.entity_id)
            : entityId
              ? String(entityId)
              : '',
        date: editing?.date ?? today(),
        invoice_date: editing?.invoice_date ?? today(),
        amount: editing ? String(editing.net) : '',
        amount_mode: 'net' as AmountMode,
        vat_rate_id: editing
            ? editing.vat_rate_id
                ? String(editing.vat_rate_id)
                : 'none'
            : defaultVat
              ? String(defaultVat.id)
              : 'none',
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
        description: editing?.description ?? '',
    });
    // Follows the payment date until the invoice date is fixed (issued, part-paid).
    const invoiceFollows = !editing?.invoice_fixed;

    const vatId = form.data.vat_rate_id === 'none' ? '' : form.data.vat_rate_id;
    const sums = sumLines(
        [
            {
                amount: form.data.amount,
                vat_rate_id: vatId,
                withheld: false,
                withheld_rate_id: '',
            },
        ],
        form.data.amount_mode,
        vatRates,
        withheldRates,
        form.data.invoice_date,
    );

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            amount: normalizeAmount(d.amount),
            vat_rate_id: d.vat_rate_id === 'none' ? null : d.vat_rate_id,
            category_id: d.category_id || null,
        }));
        const done = {
            onSuccess: () => onOpenChange(false),
            preserveScroll: true,
        };
        if (editing) {
            form.patch(`/payments/${editing.id}`, done);
        } else {
            form.post('/payments', done);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} className="contents">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit payment' : 'Add payment'}
                        </DialogTitle>
                        <DialogDescription>
                            What the customer pays and when. It is matched at
                            the bank; once paid in full it counts as paid and
                            issued.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {!entityId && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="payment-customer">
                                    Customer
                                </Label>
                                <Combobox
                                    id="payment-customer"
                                    value={form.data.entity_id}
                                    onChange={(v) =>
                                        form.setData('entity_id', v)
                                    }
                                    options={customers.map((c) => ({
                                        value: String(c.id),
                                        label: c.name,
                                    }))}
                                    placeholder="Choose a customer"
                                    searchPlaceholder="Search customers…"
                                    emptyText="No customers found."
                                />
                                <InputError message={form.errors.entity_id} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="payment-date">Payment date</Label>
                            <DateField
                                id="payment-date"
                                value={form.data.date}
                                showCalendar={false}
                                required
                                onChange={(iso) => {
                                    form.setData((d) => ({
                                        ...d,
                                        date: iso,
                                        invoice_date: invoiceFollows
                                            ? iso
                                            : d.invoice_date,
                                    }));
                                }}
                            />
                            <InputError message={form.errors.date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-invoice">
                                Invoice date
                            </Label>
                            <DateField
                                id="payment-invoice"
                                value={form.data.invoice_date}
                                showCalendar={false}
                                required
                                onChange={(iso) =>
                                    form.setData('invoice_date', iso)
                                }
                            />
                            <span className="text-muted-foreground text-xs">
                                {invoiceFollows
                                    ? 'Follows the payment date.'
                                    : 'Stays put (issued or part-paid).'}
                            </span>
                            <InputError message={form.errors.invoice_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payment-amount">Amount</Label>
                            <div className="flex gap-2">
                                <AmountInput
                                    id="payment-amount"
                                    value={form.data.amount}
                                    onChange={(v) => form.setData('amount', v)}
                                    required
                                    className="min-w-0 flex-1"
                                />
                                <div className="bg-muted inline-flex shrink-0 rounded-md p-0.5">
                                    {(['net', 'total'] as AmountMode[]).map(
                                        (m) => (
                                            <button
                                                key={m}
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'amount_mode',
                                                        m,
                                                    )
                                                }
                                                className={cn(
                                                    'rounded px-2 text-xs font-medium capitalize',
                                                    form.data.amount_mode === m
                                                        ? 'bg-background shadow-xs'
                                                        : 'text-muted-foreground',
                                                )}
                                                aria-pressed={
                                                    form.data.amount_mode === m
                                                }
                                            >
                                                {m}
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-vat">VAT</Label>
                            <Select
                                value={form.data.vat_rate_id}
                                onValueChange={(v) =>
                                    form.setData('vat_rate_id', v)
                                }
                            >
                                <SelectTrigger id="payment-vat">
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

                        <div className="text-muted-foreground grid grid-cols-3 gap-2 text-sm tabular-nums sm:col-span-2">
                            <span>Net {formatAmount(sums.net)}</span>
                            <span>VAT {formatAmount(sums.vat)}</span>
                            <span className="text-foreground font-medium">
                                Total {formatAmount(sums.total)}
                            </span>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payment-wallet">Wallet</Label>
                            <Select
                                value={form.data.wallet_id}
                                onValueChange={(v) =>
                                    form.setData('wallet_id', v)
                                }
                            >
                                <SelectTrigger id="payment-wallet">
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
                            <InputError message={form.errors.wallet_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-category">Category</Label>
                            <Combobox
                                id="payment-category"
                                value={form.data.category_id}
                                onChange={(v) => form.setData('category_id', v)}
                                options={incomeCategories.map((c) => ({
                                    value: String(c.id),
                                    label: c.name,
                                }))}
                                placeholder="— None —"
                                searchPlaceholder="Search categories…"
                                emptyText="No categories found."
                                allowNone
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="payment-description">
                                Description
                            </Label>
                            <Input
                                id="payment-description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="e.g. Website — deposit"
                            />
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

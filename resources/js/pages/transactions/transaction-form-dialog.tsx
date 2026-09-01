import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
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
import { cn } from '@/lib/utils';

type Option = { id: number; name: string };
type Category = { id: number; name: string; type: string };
type Rate = { id: number; name: string; rate: string };
type VatLine = { net: string; vat_rate_id: number | null };
type WithheldLine = { net: string; withheld_rate_id: number | null };

export type EditableTransaction = {
    id: number;
    type: string;
    date: string;
    invoice_date: string;
    description: string;
    entity_id: number | null;
    category_id: number | null;
    wallet_id: number;
    to_wallet_id: number | null;
    net: string;
    vat_rate_id: number | null;
    vat_lines: VatLine[];
    withheld_lines: WithheldLine[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallets: Option[];
    entities: Option[];
    categories: Category[];
    vatRates: Rate[];
    withheldRates: Rate[];
    editing?: EditableTransaction | null;
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
    withheldRates,
    editing,
}: Props) {
    const editingWithheld = editing?.withheld_lines?.[0];

    const form = useForm(
        editing
            ? {
                  type: editing.type,
                  date: editing.date.slice(0, 10),
                  invoice_date: editing.invoice_date.slice(0, 10),
                  description: editing.description,
                  entity_id: editing.entity_id ? String(editing.entity_id) : '',
                  category_id: editing.category_id
                      ? String(editing.category_id)
                      : '',
                  wallet_id: String(editing.wallet_id),
                  to_wallet_id: editing.to_wallet_id
                      ? String(editing.to_wallet_id)
                      : '',
                  net: editing.net,
                  amount_mode: 'net' as 'net' | 'total',
                  lines:
                      editing.vat_lines.length > 0
                          ? editing.vat_lines.map((l) => ({
                                amount: l.net,
                                vat_rate_id: l.vat_rate_id
                                    ? String(l.vat_rate_id)
                                    : '',
                            }))
                          : [
                                {
                                    amount: editing.net,
                                    vat_rate_id: editing.vat_rate_id
                                        ? String(editing.vat_rate_id)
                                        : '',
                                },
                            ],
                  withheld_net: editingWithheld ? editingWithheld.net : '',
                  withheld_rate_id: editingWithheld?.withheld_rate_id
                      ? String(editingWithheld.withheld_rate_id)
                      : '',
              }
            : {
                  type: 'expense',
                  date: today(),
                  invoice_date: today(),
                  description: '',
                  entity_id: '',
                  category_id: '',
                  wallet_id: wallets[0] ? String(wallets[0].id) : '',
                  to_wallet_id: '',
                  net: '',
                  amount_mode: 'net' as 'net' | 'total',
                  lines: [{ amount: '', vat_rate_id: '' }],
                  withheld_net: '',
                  withheld_rate_id: '',
              },
    );

    // Invoice date follows the transaction date until the user edits it directly
    // (re-derived on edit: an already-diverged invoice date stays independent).
    const [invoiceDateTouched, setInvoiceDateTouched] = useState(
        editing
            ? editing.invoice_date.slice(0, 10) !== editing.date.slice(0, 10)
            : false,
    );

    const isTransfer = form.data.type === 'transfer';

    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );

    const transferAmount = parseAmount(form.data.net);

    // Net/VAT summed across the VAT lines, interpreting each typed amount by the
    // single Net/Total mode. Total mode anchors VAT to (total − net) so a line
    // reconstructs exactly and never drifts a cent from double-rounding.
    let net = 0;
    let vat = 0;
    for (const line of form.data.lines) {
        const amount = parseAmount(line.amount);
        const rate = vatRates.find((r) => String(r.id) === line.vat_rate_id);
        const pct = rate ? Number(rate.rate) : 0;
        if (form.data.amount_mode === 'total') {
            const lineNet = round2(amount / (1 + pct / 100));
            net += lineNet;
            vat += round2(amount - lineNet);
        } else {
            net += amount;
            vat += round2((amount * pct) / 100);
        }
    }
    net = round2(net);
    vat = round2(vat);

    const withheldBase = parseAmount(form.data.withheld_net);
    const withheldRate = withheldRates.find(
        (r) => String(r.id) === form.data.withheld_rate_id,
    );
    const withheld = withheldRate
        ? round2((withheldBase * Number(withheldRate.rate)) / 100)
        : 0;

    const total = round2(net + vat - withheld);

    const errors = form.errors as Record<string, string | undefined>;
    const netError = isTransfer ? form.errors.net : errors['lines.0.amount'];

    function changeType(value: string) {
        form.setData('type', value);
        form.setData('category_id', '');
    }

    function setLine(i: number, patch: Partial<(typeof form.data.lines)[0]>) {
        form.setData(
            'lines',
            form.data.lines.map((l, idx) =>
                idx === i ? { ...l, ...patch } : l,
            ),
        );
    }

    function addLine() {
        form.setData('lines', [
            ...form.data.lines,
            { amount: '', vat_rate_id: '' },
        ]);
    }

    function removeLine(i: number) {
        form.setData(
            'lines',
            form.data.lines.filter((_, idx) => idx !== i),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => {
            if (data.type === 'transfer') {
                return {
                    type: data.type,
                    date: data.date,
                    invoice_date: data.invoice_date,
                    description: data.description,
                    wallet_id: data.wallet_id,
                    to_wallet_id: data.to_wallet_id,
                    net: String(data.net).replace(',', '.'),
                };
            }

            return {
                type: data.type,
                date: data.date,
                invoice_date: data.invoice_date,
                description: data.description,
                entity_id: data.entity_id || null,
                category_id: data.category_id || null,
                wallet_id: data.wallet_id,
                amount_mode: data.amount_mode,
                lines: data.lines.map((l) => ({
                    amount: String(l.amount).replace(',', '.'),
                    vat_rate_id: l.vat_rate_id || null,
                })),
                withheld_lines: data.withheld_net
                    ? [
                          {
                              net: String(data.withheld_net).replace(',', '.'),
                              withheld_rate_id: data.withheld_rate_id || null,
                          },
                      ]
                    : [],
            };
        });

        if (editing) {
            form.patch(`/transactions/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.post('/transactions', {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    form.reset();
                },
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit transaction' : 'Add transaction'}
                        </DialogTitle>
                        <DialogDescription>
                            Income and expenses carry VAT (and optional
                            withholding); a transfer moves money between
                            wallets.
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
                                    <SelectItem value="transfer">
                                        Transfer
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
                                onChange={(e) => {
                                    form.setData('date', e.target.value);
                                    if (!invoiceDateTouched) {
                                        form.setData(
                                            'invoice_date',
                                            e.target.value,
                                        );
                                    }
                                }}
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
                                onChange={(e) => {
                                    form.setData(
                                        'invoice_date',
                                        e.target.value,
                                    );
                                    setInvoiceDateTouched(true);
                                }}
                                required
                            />
                            <InputError message={form.errors.invoice_date} />
                        </div>

                        {!isTransfer && (
                            <div className="grid gap-2">
                                <Label htmlFor="entity_id">Entity</Label>
                                <Combobox
                                    id="entity_id"
                                    value={form.data.entity_id}
                                    onChange={(v) =>
                                        form.setData('entity_id', v)
                                    }
                                    options={entities.map((entity) => ({
                                        value: String(entity.id),
                                        label: entity.name,
                                    }))}
                                    placeholder="— None —"
                                    searchPlaceholder="Search entities…"
                                    emptyText="No entities found."
                                    allowNone
                                />
                            </div>
                        )}

                        {!isTransfer && (
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <Combobox
                                    id="category_id"
                                    value={form.data.category_id}
                                    onChange={(v) =>
                                        form.setData('category_id', v)
                                    }
                                    options={availableCategories.map(
                                        (category) => ({
                                            value: String(category.id),
                                            label: category.name,
                                        }),
                                    )}
                                    placeholder="— None —"
                                    searchPlaceholder="Search categories…"
                                    emptyText="No categories found."
                                    allowNone
                                />
                            </div>
                        )}

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
                                <InputError
                                    message={form.errors.to_wallet_id}
                                />
                            </div>
                        )}

                        {isTransfer ? (
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
                                <InputError message={netError} />
                            </div>
                        ) : (
                            <div className="grid gap-2 sm:col-span-2">
                                <div className="flex items-center justify-between">
                                    <Label>Amount</Label>
                                    <div className="inline-flex rounded-md border p-0.5 text-xs">
                                        {(['net', 'total'] as const).map(
                                            (mode) => (
                                                <button
                                                    key={mode}
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData(
                                                            'amount_mode',
                                                            mode,
                                                        )
                                                    }
                                                    className={cn(
                                                        'rounded px-2 py-1 capitalize',
                                                        form.data
                                                            .amount_mode ===
                                                            mode &&
                                                            'bg-muted font-medium',
                                                    )}
                                                >
                                                    {mode}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>

                                {form.data.lines.map((line, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2"
                                    >
                                        <Input
                                            inputMode="decimal"
                                            value={line.amount}
                                            onChange={(e) =>
                                                setLine(i, {
                                                    amount: e.target.value,
                                                })
                                            }
                                            placeholder={
                                                form.data.amount_mode ===
                                                'total'
                                                    ? 'Total'
                                                    : 'Net'
                                            }
                                            required
                                            className="flex-1"
                                        />
                                        <Select
                                            value={line.vat_rate_id || NONE}
                                            onValueChange={(v) =>
                                                setLine(i, {
                                                    vat_rate_id:
                                                        v === NONE ? '' : v,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue placeholder="VAT" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    No VAT
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
                                        {form.data.lines.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeLine(i)}
                                                aria-label="Remove VAT line"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                <InputError message={netError} />
                                <button
                                    type="button"
                                    onClick={addLine}
                                    className="text-primary self-start text-sm"
                                >
                                    + Add VAT line
                                </button>
                            </div>
                        )}

                        {!isTransfer && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="withheld_net">
                                        Withholding base (optional)
                                    </Label>
                                    <Input
                                        id="withheld_net"
                                        inputMode="decimal"
                                        value={form.data.withheld_net}
                                        onChange={(e) =>
                                            form.setData(
                                                'withheld_net',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0"
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="withheld_rate_id">
                                        Withholding rate
                                    </Label>
                                    <Select
                                        value={
                                            form.data.withheld_rate_id || NONE
                                        }
                                        onValueChange={(v) =>
                                            form.setData(
                                                'withheld_rate_id',
                                                v === NONE ? '' : v,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="withheld_rate_id">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                — None —
                                            </SelectItem>
                                            {withheldRates.map((rate) => (
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
                            </>
                        )}

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

                        {isTransfer ? (
                            <div className="bg-muted/50 rounded-lg p-3 text-sm tabular-nums sm:col-span-2">
                                <span className="text-muted-foreground text-xs">
                                    Amount moved
                                </span>
                                <div className="font-medium">
                                    {formatAmount(transferAmount)}
                                </div>
                            </div>
                        ) : (
                            <div className="bg-muted/50 grid grid-cols-4 gap-2 rounded-lg p-3 text-sm tabular-nums sm:col-span-2">
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
                                        Withheld
                                    </div>
                                    {formatAmount(withheld)}
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
                        )}
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

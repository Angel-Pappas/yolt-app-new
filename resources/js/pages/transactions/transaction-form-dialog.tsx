import { type Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    type CrudField,
    CrudFormDialog,
} from '@/components/crud/crud-form-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
    type FinanceLookups,
    useFinanceLookups,
} from '@/components/transactions/lookups';
import { CategoryFormDialog } from '@/pages/categories/category-form-dialog';
import {
    entityDescription,
    entityFields,
} from '@/pages/entities/entity-fields';
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
import { formatAmount } from '@/lib/format';
import { cn } from '@/lib/utils';

type Rate = { id: number; name: string; rate: string };
type VatLine = { net: string; vat_rate_id: number | null; position: number };
type WithheldLine = {
    net: string;
    withheld_rate_id: number | null;
    position: number;
};

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
    fmy_amount: string | null;
    efka_employee_amount: string | null;
    efka_employer_amount: string | null;
    vat_lines: VatLine[];
    withheld_lines: WithheldLine[];
};

/** The exact category name that turns the form into a payroll entry. */
const PAYROLL_CATEGORY = 'Payroll';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
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

/** The percentage of the rate a select id points at, or 0 when unset/unknown. */
function ratePct(rates: Rate[], id: string): number {
    const rate = rates.find((r) => String(r.id) === id);
    return rate ? Number(rate.rate) : 0;
}

const NONE = 'none';

type Line = {
    amount: string;
    vat_rate_id: string;
    withheld: boolean;
    withheld_rate_id: string;
};

/** Net / VAT / withheld for one line, interpreted by the single Net/Total mode.
 *  Total mode reverses the net out of the cash total (net + VAT − withheld) and
 *  anchors VAT so the line reconstructs to the exact typed total. */
function lineCalc(
    line: Line,
    mode: 'net' | 'total',
    vatRates: Rate[],
    withheldRates: Rate[],
): { net: number; vat: number; withheld: number } {
    const amount = parseAmount(line.amount);
    const v = ratePct(vatRates, line.vat_rate_id);
    const w = line.withheld ? ratePct(withheldRates, line.withheld_rate_id) : 0;

    if (mode === 'total') {
        const denom = 1 + (v - w) / 100;
        const net = denom > 0 ? round2(amount / denom) : amount;
        const withheld = round2((net * w) / 100);
        const vat = round2(amount - net + withheld);
        return { net, vat, withheld };
    }
    const net = amount;
    return {
        net,
        vat: round2((net * v) / 100),
        withheld: round2((net * w) / 100),
    };
}

export function TransactionFormDialog({ open, onOpenChange, editing }: Props) {
    // Lookups come from the globally-shared source, so this form works wherever it's
    // opened without each page having to pass them.
    const { wallets, entities, categories, vatRates, withheldRates } =
        useFinanceLookups();
    // The withholding rate a freshly-toggled line gets: the 20% one (the usual
    // Greek contractor rate) when it exists, else the first available rate.
    const defaultWithheldRateId = (): string => {
        const twenty = withheldRates.find((r) => Number(r.rate) === 20);
        return twenty
            ? String(twenty.id)
            : withheldRates[0]
              ? String(withheldRates[0].id)
              : '';
    };

    const emptyLine = (): Line => ({
        amount: '',
        vat_rate_id: '',
        withheld: false,
        withheld_rate_id: '',
    });

    // Re-couple each stored VAT line with its withheld line (matched by position),
    // so an edited transaction shows the withholding toggle lit on the right lines.
    const editingLines = (): Line[] => {
        if (!editing) return [emptyLine()];
        const withheldByPosition = new Map(
            editing.withheld_lines.map((w) => [w.position, w]),
        );
        const source: VatLine[] =
            editing.vat_lines.length > 0
                ? editing.vat_lines
                : [
                      {
                          net: editing.net,
                          vat_rate_id: editing.vat_rate_id,
                          position: 0,
                      },
                  ];
        return source.map((l) => {
            const w = withheldByPosition.get(l.position);
            return {
                amount: l.net,
                vat_rate_id: l.vat_rate_id ? String(l.vat_rate_id) : '',
                withheld: w != null,
                withheld_rate_id: w?.withheld_rate_id
                    ? String(w.withheld_rate_id)
                    : '',
            };
        });
    };

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
                  fmy: editing.fmy_amount ?? '',
                  efka_employee: editing.efka_employee_amount ?? '',
                  efka_employer: editing.efka_employer_amount ?? '',
                  amount_mode: 'net' as 'net' | 'total',
                  lines: editingLines(),
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
                  fmy: '',
                  efka_employee: '',
                  efka_employer: '',
                  amount_mode: 'net' as 'net' | 'total',
                  lines: [emptyLine()],
              },
    );

    // Invoice date follows the transaction date until the user edits it directly
    // (re-derived on edit: an already-diverged invoice date stays independent).
    const [invoiceDateTouched, setInvoiceDateTouched] = useState(
        editing
            ? editing.invoice_date.slice(0, 10) !== editing.date.slice(0, 10)
            : false,
    );

    // The "+ Add entity/category" dialogs opened from beside those fields. They open
    // over the (preserved) transaction form; their `key` bumps on open for a fresh form.
    const [addEntityOpen, setAddEntityOpen] = useState(false);
    const [addEntityKey, setAddEntityKey] = useState(0);
    const [addCategoryOpen, setAddCategoryOpen] = useState(false);
    const [addCategoryKey, setAddCategoryKey] = useState(0);

    const isTransfer = form.data.type === 'transfer';

    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );

    // Which entities the picker offers: income → customers; expense → the payee
    // types (suppliers, contractors, employees, the State). An already-selected
    // entity is always kept, so editing a legacy/unclassified (Cheese) row never
    // drops its entity.
    const entityTypesForType =
        form.data.type === 'income'
            ? ['customer']
            : ['supplier', 'contractor', 'employee', 'state'];
    const availableEntities = entities.filter(
        (e) =>
            (e.type !== null && entityTypesForType.includes(e.type)) ||
            String(e.id) === form.data.entity_id,
    );

    // Inline "+ Add entity": income adds a customer (type locked); expense lets the
    // user pick which payee type, so the new entity lands in the right list.
    const entityAddIsCustomer = form.data.type === 'income';
    const entityAddFields: CrudField[] = entityAddIsCustomer
        ? entityFields
        : [
              { key: 'name', label: 'Name', type: 'text', required: true },
              {
                  key: 'type',
                  label: 'Type',
                  type: 'select',
                  options: [
                      { value: 'supplier', label: 'Supplier' },
                      { value: 'contractor', label: 'Contractor' },
                      { value: 'employee', label: 'Employee' },
                  ],
                  placeholder: 'Choose a type',
              },
              {
                  key: 'vat_number',
                  label: 'VAT number (optional)',
                  type: 'text',
              },
          ];

    const selectedCategory = categories.find(
        (c) => String(c.id) === form.data.category_id,
    );
    const isPayroll =
        !isTransfer && selectedCategory?.name === PAYROLL_CATEGORY;

    // Payroll figures (all manual): To Pay = Net − FMY − EFKA ee is the cash the
    // employee actually receives (and what the wallet moves by); Total Cost =
    // Net + EFKA er is informational — employer EFKA is a liability paid later.
    const payNet = parseAmount(form.data.net);
    const payFmy = parseAmount(form.data.fmy);
    const payEfkaEe = parseAmount(form.data.efka_employee);
    const payEfkaEr = parseAmount(form.data.efka_employer);
    const payToPay = round2(payNet - payFmy - payEfkaEe);
    const payTotalCost = round2(payNet + payEfkaEr);

    const transferAmount = parseAmount(form.data.net);

    // Net / VAT / withheld summed across the amount lines (each interpreted by the
    // single Net/Total mode). Withholding is shown only when a line has it on.
    let net = 0;
    let vat = 0;
    let withheld = 0;
    for (const line of form.data.lines) {
        const c = lineCalc(
            line,
            form.data.amount_mode,
            vatRates,
            withheldRates,
        );
        net += c.net;
        vat += c.vat;
        withheld += c.withheld;
    }
    net = round2(net);
    vat = round2(vat);
    withheld = round2(withheld);

    const hasWithheld = form.data.lines.some((l) => l.withheld);
    const total = round2(net + vat - withheld);

    const errors = form.errors as Record<string, string | undefined>;
    const netError = isTransfer ? form.errors.net : errors['lines.0.amount'];

    function changeType(value: string) {
        form.setData('type', value);
        form.setData('category_id', '');
    }

    function setLine(i: number, patch: Partial<Line>) {
        form.setData(
            'lines',
            form.data.lines.map((l, idx) =>
                idx === i ? { ...l, ...patch } : l,
            ),
        );
    }

    function addLine() {
        form.setData('lines', [...form.data.lines, emptyLine()]);
    }

    function removeLine(i: number) {
        form.setData(
            'lines',
            form.data.lines.filter((_, idx) => idx !== i),
        );
    }

    // The per-line W button: turning it on seeds the 20% rate (kept if already set).
    function toggleWithheld(i: number) {
        const line = form.data.lines[i];
        setLine(
            i,
            line.withheld
                ? { withheld: false }
                : {
                      withheld: true,
                      withheld_rate_id:
                          line.withheld_rate_id || defaultWithheldRateId(),
                  },
        );
    }

    function openAddEntity() {
        setAddEntityKey((k) => k + 1);
        setAddEntityOpen(true);
    }

    function openAddCategory() {
        setAddCategoryKey((k) => k + 1);
        setAddCategoryOpen(true);
    }

    // After the add dialog saves (which reloaded the shared lookups while preserving
    // this form), select the newly-created record — the one whose id wasn't there before.
    function lookupsOf(page: Page): FinanceLookups | undefined {
        return (page.props as { financeLookups?: FinanceLookups })
            .financeLookups;
    }

    function selectNewEntity(page: Page) {
        const list = lookupsOf(page)?.entities ?? [];
        const added = list.find((e) => !entities.some((o) => o.id === e.id));
        if (added) form.setData('entity_id', String(added.id));
    }

    function selectNewCategory(page: Page) {
        const list = lookupsOf(page)?.categories ?? [];
        // Match the transaction's type — with "Both", two are created and we want the
        // one that fits this transaction.
        const added = list.find(
            (c) =>
                c.type === form.data.type &&
                !categories.some((o) => o.id === c.id),
        );
        if (added) form.setData('category_id', String(added.id));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        doSubmit('close');
    }

    // Add flow: 'close' saves and closes; 'new' saves and resets to defaults for
    // an unrelated next entry; 'same' saves and keeps everything except the typed
    // amounts, for entering several similar transactions in a row.
    function doSubmit(mode: 'close' | 'new' | 'same') {
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

            const cat = categories.find(
                (c) => String(c.id) === data.category_id,
            );
            if (cat?.name === PAYROLL_CATEGORY) {
                const amount = (v: string) =>
                    v ? String(v).replace(',', '.') : null;
                return {
                    type: data.type,
                    date: data.date,
                    invoice_date: data.invoice_date,
                    description: data.description,
                    entity_id: data.entity_id || null,
                    category_id: data.category_id || null,
                    wallet_id: data.wallet_id,
                    net: String(data.net).replace(',', '.'),
                    fmy_amount: amount(data.fmy),
                    efka_employee_amount: amount(data.efka_employee),
                    efka_employer_amount: amount(data.efka_employer),
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
                    // Only send a withheld rate when the line's W toggle is on.
                    withheld_rate_id:
                        l.withheld && l.withheld_rate_id
                            ? l.withheld_rate_id
                            : null,
                })),
            };
        });

        if (editing) {
            form.patch(`/transactions/${editing.id}`, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
            return;
        }

        form.post('/transactions', {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                if (mode === 'close') {
                    onOpenChange(false);
                    form.reset();
                    setInvoiceDateTouched(false);
                } else if (mode === 'new') {
                    form.reset();
                    setInvoiceDateTouched(false);
                } else {
                    // 'same' — keep the fields (incl. each line's VAT/withholding
                    // setup and the payroll category), blank only the typed amounts.
                    form.setData(
                        'lines',
                        form.data.lines.map((l) => ({ ...l, amount: '' })),
                    );
                    form.setData('net', '');
                    form.setData('fmy', '');
                    form.setData('efka_employee', '');
                    form.setData('efka_employer', '');
                }
            },
        });
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-2xl">
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit transaction'
                                    : 'Add transaction'}
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
                                <DateField
                                    id="date"
                                    value={form.data.date}
                                    showCalendar={false}
                                    required
                                    onChange={(iso) => {
                                        form.setData('date', iso);
                                        if (!invoiceDateTouched) {
                                            form.setData('invoice_date', iso);
                                        }
                                    }}
                                />
                                <InputError message={form.errors.date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invoice_date">
                                    Invoice date
                                </Label>
                                <DateField
                                    id="invoice_date"
                                    value={form.data.invoice_date}
                                    showCalendar={false}
                                    required
                                    onChange={(iso) => {
                                        form.setData('invoice_date', iso);
                                        setInvoiceDateTouched(true);
                                    }}
                                />
                                <InputError
                                    message={form.errors.invoice_date}
                                />
                            </div>

                            {!isTransfer && (
                                <div className="grid gap-2">
                                    <Label htmlFor="entity_id">Entity</Label>
                                    <div className="flex gap-2">
                                        <div className="min-w-0 flex-1">
                                            <Combobox
                                                id="entity_id"
                                                value={form.data.entity_id}
                                                onChange={(v) =>
                                                    form.setData('entity_id', v)
                                                }
                                                options={availableEntities.map(
                                                    (entity) => ({
                                                        value: String(
                                                            entity.id,
                                                        ),
                                                        label: entity.name,
                                                    }),
                                                )}
                                                placeholder="— None —"
                                                searchPlaceholder="Search entities…"
                                                emptyText="No entities found."
                                                allowNone
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            onClick={openAddEntity}
                                            aria-label="Add entity"
                                            title="Add entity"
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {!isTransfer && (
                                <div className="grid gap-2">
                                    <Label htmlFor="category_id">
                                        Category
                                    </Label>
                                    <div className="flex gap-2">
                                        <div className="min-w-0 flex-1">
                                            <Combobox
                                                id="category_id"
                                                value={form.data.category_id}
                                                onChange={(v) =>
                                                    form.setData(
                                                        'category_id',
                                                        v,
                                                    )
                                                }
                                                options={availableCategories.map(
                                                    (category) => ({
                                                        value: String(
                                                            category.id,
                                                        ),
                                                        label: category.name,
                                                    }),
                                                )}
                                                placeholder="— None —"
                                                searchPlaceholder="Search categories…"
                                                emptyText="No categories found."
                                                allowNone
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            onClick={openAddCategory}
                                            aria-label="Add category"
                                            title="Add category"
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    </div>
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
                                    <Label htmlFor="to_wallet_id">
                                        To wallet
                                    </Label>
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
                            ) : isPayroll ? (
                                <div className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="net">Net amount</Label>
                                        <Input
                                            id="net"
                                            inputMode="decimal"
                                            value={form.data.net}
                                            onChange={(e) =>
                                                form.setData(
                                                    'net',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError message={form.errors.net} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="fmy">FMY</Label>
                                        <Input
                                            id="fmy"
                                            inputMode="decimal"
                                            value={form.data.fmy}
                                            onChange={(e) =>
                                                form.setData(
                                                    'fmy',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors['fmy_amount']}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="efka_employee">
                                            EFKA Employee
                                        </Label>
                                        <Input
                                            id="efka_employee"
                                            inputMode="decimal"
                                            value={form.data.efka_employee}
                                            onChange={(e) =>
                                                form.setData(
                                                    'efka_employee',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors['efka_employee_amount']
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="efka_employer">
                                            EFKA Employer
                                        </Label>
                                        <Input
                                            id="efka_employer"
                                            inputMode="decimal"
                                            value={form.data.efka_employer}
                                            onChange={(e) =>
                                                form.setData(
                                                    'efka_employer',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors['efka_employer_amount']
                                            }
                                        />
                                    </div>
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

                                    {form.data.lines.map((line, i) => {
                                        const calc = lineCalc(
                                            line,
                                            form.data.amount_mode,
                                            vatRates,
                                            withheldRates,
                                        );
                                        return (
                                            <div
                                                key={i}
                                                className="grid gap-1.5"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        inputMode="decimal"
                                                        value={line.amount}
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                amount: e.target
                                                                    .value,
                                                            })
                                                        }
                                                        placeholder={
                                                            form.data
                                                                .amount_mode ===
                                                            'total'
                                                                ? 'Total'
                                                                : 'Net'
                                                        }
                                                        required
                                                        className="flex-1"
                                                    />
                                                    <Select
                                                        value={
                                                            line.vat_rate_id ||
                                                            NONE
                                                        }
                                                        onValueChange={(v) =>
                                                            setLine(i, {
                                                                vat_rate_id:
                                                                    v === NONE
                                                                        ? ''
                                                                        : v,
                                                            })
                                                        }
                                                    >
                                                        <SelectTrigger className="w-28">
                                                            <SelectValue placeholder="VAT" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value={NONE}
                                                            >
                                                                No VAT
                                                            </SelectItem>
                                                            {vatRates.map(
                                                                (rate) => (
                                                                    <SelectItem
                                                                        key={
                                                                            rate.id
                                                                        }
                                                                        value={String(
                                                                            rate.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            rate.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <Button
                                                        type="button"
                                                        variant={
                                                            line.withheld
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        size="icon"
                                                        onClick={() =>
                                                            toggleWithheld(i)
                                                        }
                                                        disabled={
                                                            withheldRates.length ===
                                                            0
                                                        }
                                                        aria-pressed={
                                                            line.withheld
                                                        }
                                                        aria-label="Toggle withholding tax"
                                                        title="Withholding tax"
                                                        className="shrink-0 font-semibold"
                                                    >
                                                        W
                                                    </Button>
                                                    {form.data.lines.length >
                                                        1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                removeLine(i)
                                                            }
                                                            aria-label="Remove amount line"
                                                        >
                                                            <X className="size-4" />
                                                        </Button>
                                                    )}
                                                </div>

                                                {line.withheld && (
                                                    <div className="flex items-center gap-2 pl-3">
                                                        <Input
                                                            readOnly
                                                            tabIndex={-1}
                                                            value={formatAmount(
                                                                calc.net,
                                                            )}
                                                            aria-label="Withholding base"
                                                            className="bg-muted/50 text-muted-foreground flex-1"
                                                        />
                                                        <Select
                                                            value={
                                                                line.withheld_rate_id ||
                                                                NONE
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setLine(i, {
                                                                    withheld_rate_id:
                                                                        v ===
                                                                        NONE
                                                                            ? ''
                                                                            : v,
                                                                })
                                                            }
                                                        >
                                                            <SelectTrigger className="w-28">
                                                                <SelectValue placeholder="Rate" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {withheldRates.map(
                                                                    (rate) => (
                                                                        <SelectItem
                                                                            key={
                                                                                rate.id
                                                                            }
                                                                            value={String(
                                                                                rate.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                rate.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        <span className="w-[72px] shrink-0 text-right text-sm text-red-600 tabular-nums dark:text-red-500">
                                                            −
                                                            {formatAmount(
                                                                calc.withheld,
                                                            )}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
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

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
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
                            ) : isPayroll ? (
                                <div className="bg-muted/50 grid grid-cols-3 gap-2 rounded-lg p-3 text-sm tabular-nums sm:col-span-2">
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            Net
                                        </div>
                                        {formatAmount(payNet)}
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            FMY
                                        </div>
                                        <span className="text-red-600 dark:text-red-500">
                                            −{formatAmount(payFmy)}
                                        </span>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            EFKA ee
                                        </div>
                                        <span className="text-red-600 dark:text-red-500">
                                            −{formatAmount(payEfkaEe)}
                                        </span>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            To Pay
                                        </div>
                                        <span className="font-medium">
                                            {formatAmount(payToPay)}
                                        </span>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            EFKA er
                                        </div>
                                        {formatAmount(payEfkaEr)}
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            Total Cost
                                        </div>
                                        <span className="font-medium">
                                            {formatAmount(payTotalCost)}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div
                                    className={cn(
                                        'bg-muted/50 grid gap-2 rounded-lg p-3 text-sm tabular-nums sm:col-span-2',
                                        hasWithheld
                                            ? 'grid-cols-4'
                                            : 'grid-cols-3',
                                    )}
                                >
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
                                    {hasWithheld && (
                                        <div>
                                            <div className="text-muted-foreground text-xs">
                                                Withheld
                                            </div>
                                            <span className="text-red-600 dark:text-red-500">
                                                −{formatAmount(withheld)}
                                            </span>
                                        </div>
                                    )}
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
                            {editing ? (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => doSubmit('new')}
                                    >
                                        Add + New
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => doSubmit('same')}
                                    >
                                        Add + Same
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        Add
                                    </Button>
                                </>
                            )}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* The shared entity/category add dialogs — the same form as the
                Entities/Categories pages — opened by the "+" beside each field.
                preserveState keeps this transaction form intact behind them, and
                onSaved selects the new record without reopening. */}
            <CrudFormDialog
                key={`entity-${addEntityKey}`}
                open={addEntityOpen}
                onOpenChange={setAddEntityOpen}
                singular="entity"
                baseUrl="/entities"
                fields={entityAddFields}
                fixedValues={entityAddIsCustomer ? { type: 'customer' } : {}}
                description={entityDescription}
                only={['financeLookups']}
                onSaved={selectNewEntity}
            />
            <CategoryFormDialog
                key={`category-${addCategoryKey}`}
                open={addCategoryOpen}
                onOpenChange={setAddCategoryOpen}
                initialType={form.data.type === 'income' ? 'income' : 'expense'}
                only={['financeLookups']}
                onSaved={selectNewCategory}
            />
        </>
    );
}

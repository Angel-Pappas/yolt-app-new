import { type Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import {
    type CrudField,
    CrudFormDialog,
} from '@/components/crud/crud-form-dialog';
import InputError from '@/components/input-error';
import {
    type AmountLine,
    type AmountMode,
    AmountLinesEditor,
    AmountSummary,
    emptyAmountLine,
    linesPayload,
    normalizeAmount,
    optionalAmount,
    parseAmount,
    PAYROLL_CATEGORY,
    type PayrollAmounts,
    PayrollFields,
    PayrollSummary,
} from '@/components/transactions/amount-lines';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
    type FinanceLookups,
    type PlanItemOption,
    useFinanceLookups,
} from '@/components/transactions/lookups';
import { CategoryFormDialog } from '@/pages/categories/category-form-dialog';
import { entityDescription } from '@/pages/entities/entity-fields';
import { DateField } from '@/components/ui/date-field';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

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
    managed_key?: string | null;
};

/** What a new row copies when added from a bank line (see the Bank import page). */
export type BankLineTemplate = {
    type: string;
    wallet_id: number;
    to_wallet_id: number | null;
    entity_id: number | null;
    category_id: number | null;
    vat_rate_id: number | null;
    withheld_rate_id: number | null;
};

/** A bank statement line the new transaction will confirm. */
export type BankLinePrefill = {
    id: number;
    date: string;
    /** Signed: money in is positive. */
    amount: number;
    text: string;
    wallet_id: number;
    template: BankLineTemplate | null;
};

/** The form's starting values for a bank line: its day, wallet and amount, shaped
 *  like the row it resembles when there is one. */
function bankLineDefaults(line: BankLinePrefill) {
    const incoming = line.amount > 0;
    const amount = String(Math.abs(line.amount));
    const t = line.template;
    const type =
        t?.type === 'transfer' ? 'transfer' : incoming ? 'income' : 'expense';
    const other =
        t?.type === 'transfer'
            ? t.wallet_id === line.wallet_id
                ? t.to_wallet_id
                : t.wallet_id
            : null;
    return {
        type,
        date: line.date,
        invoice_date: line.date,
        entity_id: t?.entity_id ? String(t.entity_id) : '',
        category_id:
            t?.category_id && t.type === type ? String(t.category_id) : '',
        wallet_id: String(
            type === 'transfer' && incoming && other ? other : line.wallet_id,
        ),
        to_wallet_id:
            type === 'transfer'
                ? String(incoming ? line.wallet_id : (other ?? ''))
                : '',
        net: amount,
        amount_mode: 'total' as AmountMode,
        lines: [
            {
                amount,
                vat_rate_id: t?.vat_rate_id ? String(t.vat_rate_id) : '',
                withheld: t?.withheld_rate_id != null,
                withheld_rate_id: t?.withheld_rate_id
                    ? String(t.withheld_rate_id)
                    : '',
            },
        ],
    };
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing?: EditableTransaction | null;
    /**
     * Planning ▸ One-offs: a planned one-off expense (saved to the plan at
     * `/one-offs`, not to Transactions).
     */
    oneOff?: boolean;
    /** Adding from the Bank import page: the new row confirms this line. */
    bankLine?: BankLinePrefill | null;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

/** Where the form saves: Transactions, or the plan for a one-off. */
const urlFor = (oneOff: boolean) => (oneOff ? '/one-offs' : '/transactions');

/** The open plan items of an entity going the same way (income / expense). */
function planItemsFor(
    items: PlanItemOption[],
    type: string,
    entityId: string,
): PlanItemOption[] {
    if (entityId === '' || type === 'transfer') return [];
    return items.filter(
        (i) => String(i.entity_id) === entityId && i.type === type,
    );
}

/** The one plan item a new row most likely pays: closest within 45 days. */
function guessPlanItem(
    items: PlanItemOption[],
    type: string,
    entityId: string,
    date: string,
): PlanItemOption | null {
    const day = (iso: string) => new Date(`${iso}T00:00:00`).getTime() / 864e5;
    const near = planItemsFor(items, type, entityId)
        .map((i) => ({ i, gap: Math.abs(day(i.date) - day(date)) }))
        .filter((c) => c.gap <= 45)
        .sort((a, b) => a.gap - b.gap);
    if (near.length === 0) return null;
    // Two equally close: no guess.
    if (near.length > 1 && near[1].gap === near[0].gap) return null;
    return near[0].i;
}

function planItemLabel(i: PlanItemOption): string {
    const what = i.description || 'Planned';
    return `${formatDate(i.date)} · ${what} · ${formatAmount(i.open)} open`;
}

export function TransactionFormDialog({
    open,
    onOpenChange,
    editing,
    oneOff = false,
    bankLine = null,
}: Props) {
    const baseUrl = urlFor(oneOff);
    // Lookups come from the globally-shared source, so this form works wherever it's
    // opened without each page having to pass them.
    const { wallets, entities, categories, planItems } = useFinanceLookups();

    // Re-couple each stored VAT line with its withheld line (matched by position),
    // so an edited transaction shows the withholding toggle lit on the right lines.
    const editingLines = (): AmountLine[] => {
        if (!editing) return [emptyAmountLine()];
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
                  amount_mode: 'net' as AmountMode,
                  lines: editingLines(),
                  managed_key: editing.managed_key ?? '',
                  bank_fee: '',
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
                  amount_mode: 'net' as AmountMode,
                  lines: [emptyAmountLine()],
                  managed_key: '',
                  bank_fee: '',
                  ...(bankLine ? bankLineDefaults(bankLine) : {}),
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

    // "Pays planned item": the open plan items this real transaction could be — the
    // chosen entity's, same direction (an already-chosen one is always kept).
    const planOptions = oneOff
        ? []
        : planItemsFor(planItems, form.data.type, form.data.entity_id);
    const keptKey =
        form.data.managed_key !== '' &&
        !planOptions.some((i) => i.key === form.data.managed_key)
            ? form.data.managed_key
            : null;

    // Picking the entity of a new row preselects the one plan item it most likely
    // pays (same entity and direction, closest date within 45 days, if only one).
    function changeEntity(value: string) {
        form.setData((data) => ({
            ...data,
            entity_id: value,
            managed_key:
                editing || oneOff
                    ? data.managed_key
                    : (guessPlanItem(planItems, data.type, value, data.date)
                          ?.key ?? ''),
        }));
    }

    const todayIso = today();
    const isFuture = !oneOff && form.data.date > todayIso;
    // The books of this wallet are complete up to here: a change on or before it
    // changes a concluded period (allowed, but worth knowing).
    const concludedUntil =
        wallets.find((w) => String(w.id) === form.data.wallet_id)
            ?.concluded_until ?? null;
    const touchesConcluded =
        !oneOff &&
        concludedUntil !== null &&
        (form.data.date <= concludedUntil ||
            (editing !== undefined &&
                editing !== null &&
                editing.date.slice(0, 10) <= concludedUntil));

    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );

    // Which entities the picker offers: income → customers; expense → the payee
    // types (suppliers, contractors, employees, the State). Shareholders are
    // both-sided (dividends out / investment in), so they show on either side.
    // "Show all" reveals every entity for one-off cross-side cases — e.g. a supplier
    // refunding you (income against a supplier). An already-selected entity is always
    // kept, so editing a cross-side row never drops its entity.
    const [showAllEntities, setShowAllEntities] = useState(false);
    const entityTypesForType =
        form.data.type === 'income'
            ? ['customer', 'shareholder']
            : ['supplier', 'contractor', 'employee', 'state', 'shareholder'];
    const availableEntities = showAllEntities
        ? entities
        : entities.filter(
              (e) =>
                  entityTypesForType.includes(e.type) ||
                  String(e.id) === form.data.entity_id,
          );

    // Inline "+ Add entity": pick the type (side-appropriate; shareholders are
    // both-sided, so offered either way) — the new entity lands in the right list.
    const entityAddFields: CrudField[] = [
        { key: 'name', label: 'Name', type: 'text', required: true },
        {
            key: 'type',
            label: 'Type',
            type: 'select',
            required: true,
            options:
                form.data.type === 'income'
                    ? [
                          { value: 'customer', label: 'Customer' },
                          { value: 'shareholder', label: 'Shareholder' },
                      ]
                    : [
                          { value: 'supplier', label: 'Supplier' },
                          { value: 'contractor', label: 'Contractor' },
                          { value: 'employee', label: 'Employee' },
                          { value: 'shareholder', label: 'Shareholder' },
                      ],
            placeholder: 'Choose a type',
        },
        { key: 'vat_number', label: 'VAT number (optional)', type: 'text' },
    ];

    const selectedCategory = categories.find(
        (c) => String(c.id) === form.data.category_id,
    );
    const isPayroll =
        !isTransfer && selectedCategory?.name === PAYROLL_CATEGORY;

    const transferAmount = parseAmount(form.data.net);

    // A new row on a wallet that takes bank fees can add the bank's fee with it —
    // from a bank line too, when the bank booked the payment and its fee as one line.
    const feePresets = !editing
        ? (wallets.find((w) => String(w.id) === form.data.wallet_id)
              ?.fee_presets ?? null)
        : null;

    // Payroll for an employee can be calculated from the gross. On by default for a
    // new entry; an existing one keeps its saved figures until asked.
    const [autoPayroll, setAutoPayroll] = useState(!editing);
    const payrollEntityIsEmployee =
        entities.find((e) => String(e.id) === form.data.entity_id)?.type ===
        'employee';

    // The manual payroll figures, as the shared payroll fields/summary expect them.
    const payroll: PayrollAmounts = {
        net: form.data.net,
        fmy: form.data.fmy,
        efka_employee: form.data.efka_employee,
        efka_employer: form.data.efka_employer,
    };

    const errors = form.errors as Record<string, string | undefined>;
    const netError = isTransfer ? form.errors.net : errors['lines.0.amount'];

    function changeType(value: string) {
        form.setData('type', value);
        form.setData('category_id', '');
    }

    function setPayroll(patch: Partial<PayrollAmounts>) {
        form.setData((data) => ({ ...data, ...patch }));
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

    // What the server receives for each shape (transfer, payroll, amount lines).
    function payload(data: typeof form.data) {
        if (data.type === 'transfer') {
            return {
                type: data.type,
                date: data.date,
                invoice_date: data.invoice_date,
                description: data.description,
                wallet_id: data.wallet_id,
                to_wallet_id: data.to_wallet_id,
                net: normalizeAmount(data.net),
            };
        }

        const cat = categories.find((c) => String(c.id) === data.category_id);
        if (cat?.name === PAYROLL_CATEGORY) {
            return {
                type: data.type,
                date: data.date,
                invoice_date: data.invoice_date,
                description: data.description,
                entity_id: data.entity_id || null,
                category_id: data.category_id || null,
                wallet_id: data.wallet_id,
                net: normalizeAmount(data.net),
                fmy_amount: optionalAmount(data.fmy),
                efka_employee_amount: optionalAmount(data.efka_employee),
                efka_employer_amount: optionalAmount(data.efka_employer),
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
            lines: linesPayload(data.lines),
        };
    }

    // Add flow: 'close' saves and closes; 'new' saves and resets to defaults for
    // an unrelated next entry; 'same' saves and keeps everything except the typed
    // amounts, for entering several similar transactions in a row.
    function doSubmit(mode: 'close' | 'new' | 'same') {
        // A real transaction says which plan item it pays (none = '').
        const tag = oneOff
            ? {}
            : { managed_key: form.data.managed_key || null };
        const extra = {
            ...(bankLine ? { bank_line_id: bankLine.id } : {}),
            ...(feePresets
                ? { bank_fee: optionalAmount(form.data.bank_fee) }
                : {}),
        };
        form.transform((data) => ({ ...payload(data), ...tag, ...extra }));

        if (editing) {
            form.patch(`${baseUrl}/${editing.id}`, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
            return;
        }

        form.post(baseUrl, {
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
                    form.setData('bank_fee', '');
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
                                    : bankLine
                                      ? 'Add from the bank'
                                      : 'Add transaction'}
                            </DialogTitle>
                            <DialogDescription>
                                {bankLine
                                    ? `${bankLine.text} · ${formatAmount(bankLine.amount)} — saved as reconciled. With a bank fee, the row and the fee together make this line.`
                                    : 'Income and expenses carry VAT (and optional withholding); a transfer moves money between wallets.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4 sm:grid-cols-2">
                            <div
                                className={cn(
                                    'grid gap-2 sm:col-span-2',
                                    oneOff && 'hidden',
                                )}
                            >
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
                                {isFuture && (
                                    <p className="text-xs text-amber-700 dark:text-amber-400">
                                        A future date: Transactions is for what
                                        happened — something expected belongs in
                                        Planning (a one-off).
                                    </p>
                                )}
                                {touchesConcluded && (
                                    <p className="text-xs text-amber-700 dark:text-amber-400">
                                        This changes a concluded period (this
                                        wallet is complete up to{' '}
                                        {formatDate(concludedUntil)}).
                                    </p>
                                )}
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
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="entity_id">
                                            Entity
                                        </Label>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowAllEntities((v) => !v)
                                            }
                                            className="text-muted-foreground hover:text-foreground text-xs"
                                        >
                                            {showAllEntities
                                                ? 'Matching only'
                                                : 'Show all'}
                                        </button>
                                    </div>
                                    <div className="flex gap-2">
                                        <div className="min-w-0 flex-1">
                                            <Combobox
                                                id="entity_id"
                                                value={form.data.entity_id}
                                                onChange={changeEntity}
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

                            {planOptions.length + (keptKey ? 1 : 0) > 0 && (
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="managed_key">
                                        Pays planned item
                                    </Label>
                                    <Select
                                        value={form.data.managed_key || 'none'}
                                        onValueChange={(v) =>
                                            form.setData(
                                                'managed_key',
                                                v === 'none' ? '' : v,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="managed_key">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                — Nothing planned —
                                            </SelectItem>
                                            {keptKey && (
                                                <SelectItem value={keptKey}>
                                                    The item it already pays
                                                </SelectItem>
                                            )}
                                            {planOptions.map((i) => (
                                                <SelectItem
                                                    key={i.key}
                                                    value={i.key}
                                                >
                                                    {planItemLabel(i)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors['managed_key']}
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
                                    <AmountInput
                                        id="net"
                                        value={form.data.net}
                                        onChange={(v) => form.setData('net', v)}
                                        required
                                    />
                                    <InputError message={netError} />
                                </div>
                            ) : isPayroll ? (
                                <div className="sm:col-span-2">
                                    <PayrollFields
                                        value={payroll}
                                        onChange={setPayroll}
                                        auto={
                                            payrollEntityIsEmployee
                                                ? {
                                                      on: autoPayroll,
                                                      onChange: setAutoPayroll,
                                                      entityId:
                                                          form.data.entity_id,
                                                      date: form.data
                                                          .invoice_date,
                                                  }
                                                : undefined
                                        }
                                        errors={{
                                            net: form.errors.net,
                                            fmy: errors['fmy_amount'],
                                            efka_employee:
                                                errors['efka_employee_amount'],
                                            efka_employer:
                                                errors['efka_employer_amount'],
                                        }}
                                    />
                                </div>
                            ) : (
                                <div className="sm:col-span-2">
                                    <AmountLinesEditor
                                        mode={form.data.amount_mode}
                                        onModeChange={(m) =>
                                            form.setData('amount_mode', m)
                                        }
                                        lines={form.data.lines}
                                        onLinesChange={(lines) =>
                                            form.setData('lines', lines)
                                        }
                                        error={netError}
                                        date={form.data.invoice_date}
                                    />
                                </div>
                            )}

                            {feePresets && (
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="bank_fee">
                                        Bank fee (added as its own expense)
                                    </Label>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {feePresets.map((fee) => {
                                            const on =
                                                normalizeAmount(
                                                    form.data.bank_fee,
                                                ) === fee;
                                            return (
                                                <Button
                                                    key={fee}
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    aria-pressed={on}
                                                    className={
                                                        on
                                                            ? 'text-primary border-primary'
                                                            : undefined
                                                    }
                                                    onClick={() =>
                                                        form.setData(
                                                            'bank_fee',
                                                            on
                                                                ? ''
                                                                : fee.replace(
                                                                      '.',
                                                                      ',',
                                                                  ),
                                                        )
                                                    }
                                                >
                                                    {formatAmount(fee)}
                                                </Button>
                                            );
                                        })}
                                        <AmountInput
                                            id="bank_fee"
                                            className="w-28"
                                            value={form.data.bank_fee}
                                            onChange={(v) =>
                                                form.setData('bank_fee', v)
                                            }
                                            placeholder={
                                                feePresets.length > 0
                                                    ? 'Other'
                                                    : undefined
                                            }
                                        />
                                    </div>
                                    <InputError message={errors['bank_fee']} />
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
                                <div className="sm:col-span-2">
                                    <PayrollSummary value={payroll} />
                                </div>
                            ) : (
                                <div className="sm:col-span-2">
                                    <AmountSummary
                                        lines={form.data.lines}
                                        mode={form.data.amount_mode}
                                        date={form.data.invoice_date}
                                    />
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
                            {editing || bankLine ? (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {editing ? 'Save' : 'Add'}
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

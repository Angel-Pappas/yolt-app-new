import { type Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import {
    type AmountLine,
    type AmountMode,
    AmountLinesEditor,
    AmountSummary,
    emptyAmountLine,
    emptyPayroll,
    linesPayload,
    normalizeAmount,
    optionalAmount,
    PAYROLL_CATEGORY,
    type PayrollAmounts,
    PayrollFields,
    PayrollSummary,
} from '@/components/transactions/amount-lines';
import {
    type FinanceLookups,
    useFinanceLookups,
} from '@/components/transactions/lookups';
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
import { CategoryFormDialog } from '@/pages/categories/category-form-dialog';

/** One dated period: its span plus the same amount shape as a transaction. */
type Period = {
    start_date: string;
    end_date: string;
    amount_mode: AmountMode;
    lines: AmountLine[];
    payroll: PayrollAmounts;
};

export type RecurrenceEntryRecord = {
    start_date: string;
    end_date: string | null;
    amount_mode: string;
    net: string;
    fmy_amount: string | null;
    efka_employee_amount: string | null;
    efka_employer_amount: string | null;
    lines: {
        amount: string;
        vat_rate_id: number | null;
        withheld_rate_id: number | null;
        position: number;
    }[];
};

export type RecurrenceRecord = {
    id: number;
    type: string;
    description: string;
    category_id: number | null;
    wallet_id: number;
    is_payroll: boolean;
    interval_count: number;
    interval_unit: string;
    day_of_month: number | null;
    start_date: string;
    end_date: string | null;
    active: boolean;
    entries: RecurrenceEntryRecord[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entityId: number;
    entityName: string;
    /** income for a customer, expense otherwise. */
    defaultType: 'income' | 'expense';
    /** Pre-select the Payroll category (an employee's recurrence). */
    defaultPayroll: boolean;
    editing: RecurrenceRecord | null;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

/** An ISO date shifted by whole days (UTC, so no timezone drift). */
function shiftDays(iso: string, days: number): string {
    const d = new Date(`${iso}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

function emptyPeriod(start: string): Period {
    return {
        start_date: start,
        end_date: '',
        amount_mode: 'net',
        lines: [emptyAmountLine()],
        payroll: emptyPayroll(),
    };
}

/** A stored period back into form state. */
function periodFromRecord(e: RecurrenceEntryRecord): Period {
    return {
        start_date: e.start_date.slice(0, 10),
        end_date: e.end_date ? e.end_date.slice(0, 10) : '',
        amount_mode: e.amount_mode === 'total' ? 'total' : 'net',
        lines:
            e.lines.length > 0
                ? e.lines.map((l) => ({
                      amount: l.amount,
                      vat_rate_id: l.vat_rate_id ? String(l.vat_rate_id) : '',
                      withheld: l.withheld_rate_id != null,
                      withheld_rate_id: l.withheld_rate_id
                          ? String(l.withheld_rate_id)
                          : '',
                  }))
                : [emptyAmountLine()],
        payroll: {
            net: e.net && Number(e.net) !== 0 ? e.net : '',
            fmy: e.fmy_amount ?? '',
            efka_employee: e.efka_employee_amount ?? '',
            efka_employer: e.efka_employer_amount ?? '',
        },
    };
}

/**
 * Add/edit a recurring transaction. Laid out like the transaction form (Type,
 * Entity — fixed to the entity this is opened from — Category, Wallet,
 * Description), plus the recurrence: the cadence picker and a list of dated
 * periods, each with the full transaction amount editor (several VAT lines,
 * per-line withholding, Net/Total — or the payroll Net/FMY/EFKA set when the
 * category is "Payroll").
 */
export function RecurrenceFormDialog({
    open,
    onOpenChange,
    entityId,
    entityName,
    defaultType,
    defaultPayroll,
    editing,
}: Props) {
    const { wallets, categories } = useFinanceLookups();

    const payrollCategory = categories.find(
        (c) => c.name === PAYROLL_CATEGORY && c.type === 'expense',
    );

    const form = useForm(
        editing
            ? {
                  type: editing.type,
                  description: editing.description,
                  category_id: editing.category_id
                      ? String(editing.category_id)
                      : '',
                  wallet_id: String(editing.wallet_id),
                  interval_count: String(editing.interval_count),
                  interval_unit: editing.interval_unit,
                  day_of_month: editing.day_of_month
                      ? String(editing.day_of_month)
                      : '1',
                  active: editing.active,
                  periods:
                      editing.entries.length > 0
                          ? editing.entries.map(periodFromRecord)
                          : [emptyPeriod(today())],
              }
            : {
                  type: defaultType as string,
                  description: '',
                  category_id:
                      defaultPayroll && payrollCategory
                          ? String(payrollCategory.id)
                          : '',
                  wallet_id: wallets[0] ? String(wallets[0].id) : '',
                  interval_count: '1',
                  interval_unit: 'month',
                  day_of_month: '1',
                  active: true,
                  periods: [emptyPeriod(today())],
              },
    );

    // The "+ Add category" dialog opened from beside the Category field (the same
    // one the transaction form uses); its `key` bumps on open for a fresh form.
    const [addCategoryOpen, setAddCategoryOpen] = useState(false);
    const [addCategoryKey, setAddCategoryKey] = useState(0);

    const isWeek = form.data.interval_unit === 'week';
    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );
    const selectedCategory = categories.find(
        (c) => String(c.id) === form.data.category_id,
    );
    const isPayroll = selectedCategory?.name === PAYROLL_CATEGORY;

    const errors = form.errors as Record<string, string | undefined>;

    function changeType(value: string) {
        form.setData((data) => ({ ...data, type: value, category_id: '' }));
    }

    function setPeriod(i: number, patch: Partial<Period>) {
        form.setData((data) => ({
            ...data,
            periods: data.periods.map((p, idx) =>
                idx === i ? { ...p, ...patch } : p,
            ),
        }));
    }

    // Setting a later period's start closes the previous period the day before, so
    // an open-ended period hands over cleanly to the next one.
    function setPeriodStart(i: number, iso: string) {
        form.setData((data) => ({
            ...data,
            periods: data.periods.map((p, idx) => {
                if (idx === i) return { ...p, start_date: iso };
                if (idx === i - 1 && iso) {
                    return { ...p, end_date: shiftDays(iso, -1) };
                }
                return p;
            }),
        }));
    }

    // A new period copies the previous one's setup (lines with their VAT and
    // withholding, the Net/Total mode) with the amounts blanked, and starts the day
    // after the previous one ends when it has an end.
    function addPeriod() {
        const prev = form.data.periods[form.data.periods.length - 1];
        const next: Period = prev
            ? {
                  start_date: prev.end_date ? shiftDays(prev.end_date, 1) : '',
                  end_date: '',
                  amount_mode: prev.amount_mode,
                  lines: prev.lines.map((l) => ({ ...l, amount: '' })),
                  payroll: emptyPayroll(),
              }
            : emptyPeriod(today());
        form.setData('periods', [...form.data.periods, next]);
    }

    function removePeriod(i: number) {
        form.setData(
            'periods',
            form.data.periods.filter((_, idx) => idx !== i),
        );
    }

    function openAddCategory() {
        setAddCategoryKey((k) => k + 1);
        setAddCategoryOpen(true);
    }

    // After the add dialog saves (which reloaded the shared lookups while preserving
    // this form), select the newly-created category matching this recurrence's type.
    function selectNewCategory(page: Page) {
        const list =
            (page.props as { financeLookups?: FinanceLookups }).financeLookups
                ?.categories ?? [];
        const added = list.find(
            (c) =>
                c.type === form.data.type &&
                !categories.some((o) => o.id === c.id),
        );
        if (added) form.setData('category_id', String(added.id));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            entity_id: entityId,
            type: data.type,
            description: data.description,
            category_id: data.category_id || null,
            wallet_id: data.wallet_id,
            interval_count: data.interval_count,
            interval_unit: data.interval_unit,
            day_of_month:
                data.interval_unit === 'week' ? null : data.day_of_month,
            active: data.active,
            entries: data.periods.map((p) =>
                isPayroll
                    ? {
                          start_date: p.start_date,
                          end_date: p.end_date || null,
                          net: normalizeAmount(p.payroll.net || '0'),
                          fmy_amount: optionalAmount(p.payroll.fmy),
                          efka_employee_amount: optionalAmount(
                              p.payroll.efka_employee,
                          ),
                          efka_employer_amount: optionalAmount(
                              p.payroll.efka_employer,
                          ),
                      }
                    : {
                          start_date: p.start_date,
                          end_date: p.end_date || null,
                          amount_mode: p.amount_mode,
                          lines: linesPayload(p.lines),
                      },
            ),
        }));
        const opts = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        if (editing) {
            form.patch(`/recurrences/${editing.id}`, opts);
        } else {
            form.post('/recurrences', opts);
        }
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit recurring transaction'
                                    : 'New recurring transaction'}
                            </DialogTitle>
                            <DialogDescription>
                                Generates this transaction on a schedule. Add a
                                period whenever the amounts change.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="rec_type">Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={changeType}
                                >
                                    <SelectTrigger id="rec_type">
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
                                <Label htmlFor="rec_entity">Entity</Label>
                                <Input
                                    id="rec_entity"
                                    value={entityName}
                                    disabled
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="rec_category_id">
                                    Category
                                </Label>
                                <div className="flex gap-2">
                                    <div className="min-w-0 flex-1">
                                        <Combobox
                                            id="rec_category_id"
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

                            <div className="grid gap-2">
                                <Label htmlFor="rec_wallet_id">Wallet</Label>
                                <Select
                                    value={form.data.wallet_id}
                                    onValueChange={(v) =>
                                        form.setData('wallet_id', v)
                                    }
                                >
                                    <SelectTrigger id="rec_wallet_id">
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

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="rec_description">
                                    Description
                                </Label>
                                <Input
                                    id="rec_description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Office rent"
                                />
                                <InputError message={form.errors.description} />
                            </div>

                            <div className="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="interval_count">
                                        Every
                                    </Label>
                                    <Select
                                        value={form.data.interval_count}
                                        onValueChange={(v) =>
                                            form.setData('interval_count', v)
                                        }
                                    >
                                        <SelectTrigger id="interval_count">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Array.from(
                                                { length: 12 },
                                                (_, i) => i + 1,
                                            ).map((n) => (
                                                <SelectItem
                                                    key={n}
                                                    value={String(n)}
                                                >
                                                    {n}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="interval_unit">Unit</Label>
                                    <Select
                                        value={form.data.interval_unit}
                                        onValueChange={(v) =>
                                            form.setData('interval_unit', v)
                                        }
                                    >
                                        <SelectTrigger id="interval_unit">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="week">
                                                week(s)
                                            </SelectItem>
                                            <SelectItem value="month">
                                                month(s)
                                            </SelectItem>
                                            <SelectItem value="year">
                                                year(s)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                {!isWeek && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="day_of_month">
                                            On day
                                        </Label>
                                        <Input
                                            id="day_of_month"
                                            inputMode="numeric"
                                            value={form.data.day_of_month}
                                            onChange={(e) =>
                                                form.setData(
                                                    'day_of_month',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.day_of_month}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-3 sm:col-span-2">
                                <Label>Periods</Label>
                                {form.data.periods.map((period, i) => (
                                    <div
                                        key={i}
                                        className="grid gap-3 rounded-md border p-3"
                                    >
                                        <div className="flex items-start gap-2">
                                            <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2">
                                                <div className="grid gap-1">
                                                    <Label className="text-xs">
                                                        Start date
                                                    </Label>
                                                    <DateField
                                                        value={
                                                            period.start_date
                                                        }
                                                        onChange={(iso) =>
                                                            setPeriodStart(
                                                                i,
                                                                iso,
                                                            )
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `entries.${i}.start_date`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label className="text-xs">
                                                        End date (optional)
                                                    </Label>
                                                    <DateField
                                                        value={period.end_date}
                                                        onChange={(iso) =>
                                                            setPeriod(i, {
                                                                end_date: iso,
                                                            })
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `entries.${i}.end_date`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            {form.data.periods.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="mt-5 shrink-0"
                                                    onClick={() =>
                                                        removePeriod(i)
                                                    }
                                                    aria-label="Remove period"
                                                >
                                                    <X className="size-4" />
                                                </Button>
                                            )}
                                        </div>

                                        {isPayroll ? (
                                            <>
                                                <PayrollFields
                                                    idPrefix={`period-${i}-`}
                                                    value={period.payroll}
                                                    onChange={(patch) =>
                                                        setPeriod(i, {
                                                            payroll: {
                                                                ...period.payroll,
                                                                ...patch,
                                                            },
                                                        })
                                                    }
                                                    errors={{
                                                        net: errors[
                                                            `entries.${i}.net`
                                                        ],
                                                        fmy: errors[
                                                            `entries.${i}.fmy_amount`
                                                        ],
                                                        efka_employee:
                                                            errors[
                                                                `entries.${i}.efka_employee_amount`
                                                            ],
                                                        efka_employer:
                                                            errors[
                                                                `entries.${i}.efka_employer_amount`
                                                            ],
                                                    }}
                                                />
                                                <PayrollSummary
                                                    value={period.payroll}
                                                />
                                            </>
                                        ) : (
                                            <>
                                                <AmountLinesEditor
                                                    mode={period.amount_mode}
                                                    onModeChange={(m) =>
                                                        setPeriod(i, {
                                                            amount_mode: m,
                                                        })
                                                    }
                                                    lines={period.lines}
                                                    onLinesChange={(lines) =>
                                                        setPeriod(i, { lines })
                                                    }
                                                    error={
                                                        errors[
                                                            `entries.${i}.lines`
                                                        ] ??
                                                        errors[
                                                            `entries.${i}.lines.0.amount`
                                                        ]
                                                    }
                                                />
                                                <AmountSummary
                                                    lines={period.lines}
                                                    mode={period.amount_mode}
                                                />
                                            </>
                                        )}
                                    </div>
                                ))}
                                <InputError message={errors.entries} />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="justify-self-start"
                                    onClick={addPeriod}
                                >
                                    <Plus className="size-3" /> Add period
                                </Button>
                            </div>

                            {editing && (
                                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.active}
                                        onChange={(e) =>
                                            form.setData(
                                                'active',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Active
                                </label>
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
                                {editing ? 'Save' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

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

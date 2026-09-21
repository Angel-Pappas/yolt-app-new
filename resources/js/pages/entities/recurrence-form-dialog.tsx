import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { type FormEvent } from 'react';
import { useFinanceLookups } from '@/components/transactions/lookups';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import {
    Dialog,
    DialogContent,
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

const NONE = 'none';

type Entry = {
    start_date: string;
    end_date: string;
    net: string;
    fmy_amount: string;
    efka_employee_amount: string;
    efka_employer_amount: string;
};

export type RecurrenceRecord = {
    id: number;
    type: string;
    description: string;
    category_id: number | null;
    wallet_id: number;
    vat_rate_id: number | null;
    withheld_rate_id: number | null;
    is_payroll: boolean;
    interval_count: number;
    interval_unit: string;
    day_of_month: number | null;
    start_date: string;
    end_date: string | null;
    active: boolean;
    entries: {
        start_date: string;
        end_date: string | null;
        net: string;
        fmy_amount: string | null;
        efka_employee_amount: string | null;
        efka_employer_amount: string | null;
    }[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entityId: number;
    /** income for a customer, expense otherwise. */
    defaultType: 'income' | 'expense';
    defaultPayroll: boolean;
    editing: RecurrenceRecord | null;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function emptyEntry(): Entry {
    return {
        start_date: today(),
        end_date: '',
        net: '',
        fmy_amount: '',
        efka_employee_amount: '',
        efka_employer_amount: '',
    };
}

function num(v: string): string {
    return v.replace(',', '.');
}

export function RecurrenceFormDialog({
    open,
    onOpenChange,
    entityId,
    defaultType,
    defaultPayroll,
    editing,
}: Props) {
    const { wallets, categories, vatRates, withheldRates } =
        useFinanceLookups();

    const form = useForm(
        editing
            ? {
                  type: editing.type,
                  description: editing.description,
                  category_id: editing.category_id
                      ? String(editing.category_id)
                      : NONE,
                  wallet_id: String(editing.wallet_id),
                  vat_rate_id: editing.vat_rate_id
                      ? String(editing.vat_rate_id)
                      : NONE,
                  withheld_rate_id: editing.withheld_rate_id
                      ? String(editing.withheld_rate_id)
                      : NONE,
                  is_payroll: editing.is_payroll,
                  interval_count: String(editing.interval_count),
                  interval_unit: editing.interval_unit,
                  day_of_month: editing.day_of_month
                      ? String(editing.day_of_month)
                      : '1',
                  start_date: editing.start_date.slice(0, 10),
                  end_date: editing.end_date
                      ? editing.end_date.slice(0, 10)
                      : '',
                  active: editing.active,
                  entries: editing.entries.map((e) => ({
                      start_date: e.start_date.slice(0, 10),
                      end_date: e.end_date ? e.end_date.slice(0, 10) : '',
                      net: e.net,
                      fmy_amount: e.fmy_amount ?? '',
                      efka_employee_amount: e.efka_employee_amount ?? '',
                      efka_employer_amount: e.efka_employer_amount ?? '',
                  })),
              }
            : {
                  type: defaultType,
                  description: '',
                  category_id: NONE,
                  wallet_id: wallets[0] ? String(wallets[0].id) : '',
                  vat_rate_id: NONE,
                  withheld_rate_id: NONE,
                  is_payroll: defaultPayroll,
                  interval_count: '1',
                  interval_unit: 'month',
                  day_of_month: '1',
                  start_date: today(),
                  end_date: '',
                  active: true,
                  entries: [emptyEntry()],
              },
    );

    const isPayroll = form.data.is_payroll;
    const isWeek = form.data.interval_unit === 'week';
    const availableCategories = categories.filter(
        (c) => c.type === form.data.type,
    );

    function setEntry(index: number, patch: Partial<Entry>) {
        form.setData(
            'entries',
            form.data.entries.map((e, i) =>
                i === index ? { ...e, ...patch } : e,
            ),
        );
    }

    function addEntry() {
        form.setData('entries', [...form.data.entries, emptyEntry()]);
    }

    function removeEntry(index: number) {
        form.setData(
            'entries',
            form.data.entries.filter((_, i) => i !== index),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            entity_id: entityId,
            category_id: data.category_id === NONE ? null : data.category_id,
            vat_rate_id:
                data.is_payroll || data.vat_rate_id === NONE
                    ? null
                    : data.vat_rate_id,
            withheld_rate_id:
                data.is_payroll || data.withheld_rate_id === NONE
                    ? null
                    : data.withheld_rate_id,
            day_of_month:
                data.interval_unit === 'week' ? null : data.day_of_month,
            end_date: data.end_date || null,
            entries: data.entries.map((en) => ({
                start_date: en.start_date,
                end_date: en.end_date || null,
                net: num(en.net || '0'),
                fmy_amount: data.is_payroll ? num(en.fmy_amount || '0') : null,
                efka_employee_amount: data.is_payroll
                    ? num(en.efka_employee_amount || '0')
                    : null,
                efka_employer_amount: data.is_payroll
                    ? num(en.efka_employer_amount || '0')
                    : null,
            })),
        }));
        const opts = {
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Edit recurring transaction'
                                : 'New recurring transaction'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="e.g. Office rent"
                            />
                            <InputError message={form.errors.description} />
                        </div>

                        {form.data.type === 'expense' && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_payroll}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_payroll',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Payroll (Net · FMY · EFKA)
                            </label>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <Select
                                    value={form.data.category_id}
                                    onValueChange={(v) =>
                                        form.setData('category_id', v)
                                    }
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>
                                            — None —
                                        </SelectItem>
                                        {availableCategories.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.name}
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
                        </div>

                        {!isPayroll && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="vat_rate_id">
                                        VAT rate
                                    </Label>
                                    <Select
                                        value={form.data.vat_rate_id}
                                        onValueChange={(v) =>
                                            form.setData('vat_rate_id', v)
                                        }
                                    >
                                        <SelectTrigger id="vat_rate_id">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                — None —
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
                                <div className="grid gap-2">
                                    <Label htmlFor="withheld_rate_id">
                                        Withholding
                                    </Label>
                                    <Select
                                        value={form.data.withheld_rate_id}
                                        onValueChange={(v) =>
                                            form.setData('withheld_rate_id', v)
                                        }
                                    >
                                        <SelectTrigger id="withheld_rate_id">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                — None —
                                            </SelectItem>
                                            {withheldRates.map((r) => (
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
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="interval_count">Every</Label>
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
                                    <Label htmlFor="day_of_month">On day</Label>
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

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Start date</Label>
                                <DateField
                                    id="start_date"
                                    value={form.data.start_date}
                                    onChange={(iso) =>
                                        form.setData('start_date', iso)
                                    }
                                    required
                                />
                                <InputError message={form.errors.start_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="end_date">
                                    End date (optional)
                                </Label>
                                <DateField
                                    id="end_date"
                                    value={form.data.end_date}
                                    onChange={(iso) =>
                                        form.setData('end_date', iso)
                                    }
                                />
                                <InputError message={form.errors.end_date} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label>Amounts over time</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addEntry}
                                >
                                    <Plus className="size-3" /> Add change
                                </Button>
                            </div>
                            {form.data.entries.map((entry, i) => (
                                <div
                                    key={i}
                                    className="grid grid-cols-2 gap-2 rounded-md border p-2"
                                >
                                    <div className="grid gap-1">
                                        <Label className="text-xs">From</Label>
                                        <DateField
                                            value={entry.start_date}
                                            onChange={(iso) =>
                                                setEntry(i, {
                                                    start_date: iso,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Until (optional)
                                        </Label>
                                        <DateField
                                            value={entry.end_date}
                                            onChange={(iso) =>
                                                setEntry(i, { end_date: iso })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            {isPayroll ? 'Net' : 'Amount'}
                                        </Label>
                                        <Input
                                            inputMode="decimal"
                                            value={entry.net}
                                            onChange={(e) =>
                                                setEntry(i, {
                                                    net: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="flex items-end justify-end">
                                        {form.data.entries.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeEntry(i)}
                                                aria-label="Remove"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                    {isPayroll && (
                                        <>
                                            <div className="grid gap-1">
                                                <Label className="text-xs">
                                                    FMY
                                                </Label>
                                                <Input
                                                    inputMode="decimal"
                                                    value={entry.fmy_amount}
                                                    onChange={(e) =>
                                                        setEntry(i, {
                                                            fmy_amount:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label className="text-xs">
                                                    EFKA (employee)
                                                </Label>
                                                <Input
                                                    inputMode="decimal"
                                                    value={
                                                        entry.efka_employee_amount
                                                    }
                                                    onChange={(e) =>
                                                        setEntry(i, {
                                                            efka_employee_amount:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label className="text-xs">
                                                    EFKA (employer)
                                                </Label>
                                                <Input
                                                    inputMode="decimal"
                                                    value={
                                                        entry.efka_employer_amount
                                                    }
                                                    onChange={(e) =>
                                                        setEntry(i, {
                                                            efka_employer_amount:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </>
                                    )}
                                </div>
                            ))}
                            <InputError message={form.errors.entries} />
                        </div>

                        {editing && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.active}
                                    onChange={(e) =>
                                        form.setData('active', e.target.checked)
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
    );
}

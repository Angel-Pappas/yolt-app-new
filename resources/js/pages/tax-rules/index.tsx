import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { CrudFormDialog } from '@/components/crud/crud-form-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateField } from '@/components/ui/date-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStoredState } from '@/hooks/use-stored-state';
import { normalizeAmount } from '@/lib/amounts';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Bracket = { up_to: string | number | null; rates: (string | number)[] };
type AgeBand = {
    max_age: string | number;
    up_to: string | number;
    rate: string | number;
};

/** One dated set, as `App\Support\TaxRuleSet` documents it. */
type RuleData = {
    efka: Record<
        'employee_rate' | 'employer_rate' | 'monthly_cap',
        string | number
    >;
    payroll_tax: {
        salaries_per_year: string | number;
        brackets: Bracket[];
        age_bands: AgeBand[];
        reduction: {
            by_children: (string | number)[];
            extra_per_child: string | number;
            threshold: string | number;
            cut_per_1000: string | number;
            no_cut_from_children: string | number;
        };
    };
    company_tax: Record<
        | 'rate'
        | 'advance_rate'
        | 'business_levy'
        | 'installments'
        | 'first_installment_month',
        string | number
    >;
    severance: { min_daily_wage: string | number };
};

type RuleSet = {
    id: number;
    valid_from: string;
    note: string | null;
    data: RuleData;
};

const CHILDREN = ['No children', '1 child', '2', '3', '4 or more'];

/** Every number as the server wants it: `1.234,5` → `1234.5`; blank stays null. */
function normalized<T>(value: T): T {
    if (Array.isArray(value)) return value.map(normalized) as T;
    if (value !== null && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([k, v]) => [k, normalized(v)]),
        ) as T;
    }
    if (value === null || value === '') return null as T;
    return normalizeAmount(String(value)) as T;
}

function Num({
    value,
    onChange,
    label,
    suffix,
    error,
    className,
}: {
    value: string | number | null;
    onChange: (value: string) => void;
    label?: string;
    suffix?: string;
    error?: string;
    className?: string;
}) {
    const input = (
        <div className="relative">
            <Input
                inputMode="decimal"
                value={value === null ? '' : String(value).replace('.', ',')}
                onChange={(e) => onChange(e.target.value)}
                className={cn('text-right tabular-nums', suffix && 'pr-8')}
                aria-label={label}
            />
            {suffix && (
                <span className="text-muted-foreground pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm">
                    {suffix}
                </span>
            )}
        </div>
    );
    if (!label) return <div className={className}>{input}</div>;
    return (
        <div className={cn('grid gap-2', className)}>
            <Label>{label}</Label>
            {input}
            <InputError message={error} />
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">{children}</CardContent>
        </Card>
    );
}

/** The form for one dated set. Remounted (via `key`) when another set is picked. */
function RuleSetForm({ set, canDelete }: { set: RuleSet; canDelete: boolean }) {
    const form = useForm({
        valid_from: set.valid_from,
        note: set.note ?? '',
        data: set.data,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const d = form.data.data;
    const tax = d.payroll_tax;

    function setData(patch: (data: RuleData) => RuleData) {
        form.setData('data', patch(structuredClone(form.data.data)));
    }

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({ ...data, data: normalized(data.data) }));
        form.patch(`/configuration/tax-rules/${set.id}`, {
            preserveScroll: true,
        });
    }

    function destroy() {
        if (
            confirm(
                'Delete this set of rules? Dates it covered fall back to the set before it.',
            )
        ) {
            router.delete(`/configuration/tax-rules/${set.id}`);
        }
    }

    return (
        <form onSubmit={save} className="grid gap-4">
            <Card>
                <CardContent className="grid gap-4 sm:grid-cols-[14rem_1fr_auto] sm:items-end">
                    <div className="grid gap-2">
                        <Label htmlFor="valid_from">Applies from</Label>
                        <DateField
                            id="valid_from"
                            value={form.data.valid_from}
                            showCalendar={false}
                            required
                            onChange={(iso) => form.setData('valid_from', iso)}
                        />
                        <InputError message={errors.valid_from} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="note">Note</Label>
                        <Input
                            id="note"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            placeholder="e.g. the law that brought the change"
                        />
                    </div>
                    {canDelete && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={destroy}
                            aria-label="Delete this set of rules"
                            title="Delete this set of rules"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Section title="Social insurance (EFKA)">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Num
                        label="Employee share"
                        suffix="%"
                        value={d.efka.employee_rate}
                        error={errors['data.efka.employee_rate']}
                        onChange={(v) =>
                            setData((x) => {
                                x.efka.employee_rate = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="Employer share"
                        suffix="%"
                        value={d.efka.employer_rate}
                        error={errors['data.efka.employer_rate']}
                        onChange={(v) =>
                            setData((x) => {
                                x.efka.employer_rate = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="Monthly cap on insurable pay"
                        suffix="€"
                        value={d.efka.monthly_cap}
                        error={errors['data.efka.monthly_cap']}
                        onChange={(v) =>
                            setData((x) => {
                                x.efka.monthly_cap = v;
                                return x;
                            })
                        }
                    />
                </div>
            </Section>

            <Section title="Payroll tax (FMY)">
                <p className="text-muted-foreground text-sm">
                    Tax on a year's taxable pay (monthly pay after employee EFKA
                    × salaries per year), by income bracket and number of
                    dependent children.
                </p>
                {/* Six columns of rates don't fit a phone: the table scrolls inside
                    its own box there instead of pushing the page sideways. */}
                <div className="-mx-1 overflow-x-auto px-1">
                    <div className="grid min-w-[34rem] grid-cols-[minmax(7rem,1fr)_repeat(5,minmax(4.5rem,1fr))_2.25rem] items-center gap-2 text-sm">
                        <div className="text-muted-foreground">
                            Income up to
                        </div>
                        {CHILDREN.map((c) => (
                            <div
                                key={c}
                                className="text-muted-foreground text-right"
                            >
                                {c}
                            </div>
                        ))}
                        <div />
                        {tax.brackets.map((bracket, i) => (
                            <div key={i} className="contents">
                                <Num
                                    label={`Bracket ${i + 1} limit`}
                                    className="[&>label]:sr-only [&>p]:hidden"
                                    suffix="€"
                                    value={bracket.up_to}
                                    onChange={(v) =>
                                        setData((x) => {
                                            x.payroll_tax.brackets[i].up_to = v;
                                            return x;
                                        })
                                    }
                                />
                                {bracket.rates.map((rate, c) => (
                                    <Num
                                        key={c}
                                        suffix="%"
                                        value={rate}
                                        onChange={(v) =>
                                            setData((x) => {
                                                x.payroll_tax.brackets[i].rates[
                                                    c
                                                ] = v;
                                                return x;
                                            })
                                        }
                                    />
                                ))}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove bracket"
                                    disabled={tax.brackets.length === 1}
                                    onClick={() =>
                                        setData((x) => {
                                            x.payroll_tax.brackets.splice(i, 1);
                                            return x;
                                        })
                                    }
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
                <p className="text-muted-foreground text-xs">
                    Leave the last bracket's limit empty: it covers everything
                    above.
                </p>
                <InputError message={errors['data.payroll_tax.brackets']} />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="justify-self-start"
                    onClick={() =>
                        setData((x) => {
                            x.payroll_tax.brackets.push({
                                up_to: null,
                                rates: [0, 0, 0, 0, 0],
                            });
                            return x;
                        })
                    }
                >
                    + Add bracket
                </Button>

                <div className="grid gap-2 border-t pt-4">
                    <Label>Young employees</Label>
                    <p className="text-muted-foreground text-sm">
                        A lower rate on the first part of the income, by the age
                        reached in the tax year.
                    </p>
                    {tax.age_bands.map((band, i) => (
                        <div
                            key={i}
                            className="grid grid-cols-[repeat(3,minmax(0,1fr))_2.25rem] items-end gap-2"
                        >
                            <Num
                                label="Up to age"
                                value={band.max_age}
                                onChange={(v) =>
                                    setData((x) => {
                                        x.payroll_tax.age_bands[i].max_age = v;
                                        return x;
                                    })
                                }
                            />
                            <Num
                                label="On income up to"
                                suffix="€"
                                value={band.up_to}
                                onChange={(v) =>
                                    setData((x) => {
                                        x.payroll_tax.age_bands[i].up_to = v;
                                        return x;
                                    })
                                }
                            />
                            <Num
                                label="Rate"
                                suffix="%"
                                value={band.rate}
                                onChange={(v) =>
                                    setData((x) => {
                                        x.payroll_tax.age_bands[i].rate = v;
                                        return x;
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Remove age rule"
                                onClick={() =>
                                    setData((x) => {
                                        x.payroll_tax.age_bands.splice(i, 1);
                                        return x;
                                    })
                                }
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="justify-self-start"
                        onClick={() =>
                            setData((x) => {
                                x.payroll_tax.age_bands.push({
                                    max_age: '',
                                    up_to: '',
                                    rate: '',
                                });
                                return x;
                            })
                        }
                    >
                        + Add age rule
                    </Button>
                </div>

                <div className="grid gap-2 border-t pt-4">
                    <Label>Tax reduction</Label>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        {tax.reduction.by_children.map((amount, c) => (
                            <Num
                                key={c}
                                label={CHILDREN[c]}
                                suffix="€"
                                value={amount}
                                onChange={(v) =>
                                    setData((x) => {
                                        x.payroll_tax.reduction.by_children[c] =
                                            v;
                                        return x;
                                    })
                                }
                            />
                        ))}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-4">
                        <Num
                            label="More per child after the 4th"
                            suffix="€"
                            value={tax.reduction.extra_per_child}
                            onChange={(v) =>
                                setData((x) => {
                                    x.payroll_tax.reduction.extra_per_child = v;
                                    return x;
                                })
                            }
                        />
                        <Num
                            label="Cut starts above income"
                            suffix="€"
                            value={tax.reduction.threshold}
                            onChange={(v) =>
                                setData((x) => {
                                    x.payroll_tax.reduction.threshold = v;
                                    return x;
                                })
                            }
                        />
                        <Num
                            label="Cut per 1.000 € above it"
                            suffix="€"
                            value={tax.reduction.cut_per_1000}
                            onChange={(v) =>
                                setData((x) => {
                                    x.payroll_tax.reduction.cut_per_1000 = v;
                                    return x;
                                })
                            }
                        />
                        <Num
                            label="No cut from this many children"
                            value={tax.reduction.no_cut_from_children}
                            onChange={(v) =>
                                setData((x) => {
                                    x.payroll_tax.reduction.no_cut_from_children =
                                        v;
                                    return x;
                                })
                            }
                        />
                    </div>
                </div>
                <Num
                    label="Salaries per year (for the yearly tax)"
                    className="sm:max-w-56"
                    value={tax.salaries_per_year}
                    error={errors['data.payroll_tax.salaries_per_year']}
                    onChange={(v) =>
                        setData((x) => {
                            x.payroll_tax.salaries_per_year = v;
                            return x;
                        })
                    }
                />
            </Section>

            <Section title="Company income tax">
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <Num
                        label="Tax on profit"
                        suffix="%"
                        value={d.company_tax.rate}
                        error={errors['data.company_tax.rate']}
                        onChange={(v) =>
                            setData((x) => {
                                x.company_tax.rate = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="Advance for next year"
                        suffix="%"
                        value={d.company_tax.advance_rate}
                        error={errors['data.company_tax.advance_rate']}
                        onChange={(v) =>
                            setData((x) => {
                                x.company_tax.advance_rate = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="Business levy"
                        suffix="€"
                        value={d.company_tax.business_levy}
                        error={errors['data.company_tax.business_levy']}
                        onChange={(v) =>
                            setData((x) => {
                                x.company_tax.business_levy = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="Monthly installments"
                        value={d.company_tax.installments}
                        error={errors['data.company_tax.installments']}
                        onChange={(v) =>
                            setData((x) => {
                                x.company_tax.installments = v;
                                return x;
                            })
                        }
                    />
                    <Num
                        label="First installment month (1–12)"
                        value={d.company_tax.first_installment_month}
                        error={
                            errors['data.company_tax.first_installment_month']
                        }
                        onChange={(v) =>
                            setData((x) => {
                                x.company_tax.first_installment_month = v;
                                return x;
                            })
                        }
                    />
                </div>
            </Section>

            <Section title="Severance">
                <Num
                    label="Minimum daily wage (sets the cap on the pay counted)"
                    className="sm:max-w-80"
                    suffix="€"
                    value={d.severance.min_daily_wage}
                    error={errors['data.severance.min_daily_wage']}
                    onChange={(v) =>
                        setData((x) => {
                            x.severance.min_daily_wage = v;
                            return x;
                        })
                    }
                />
            </Section>

            <div>
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
            </div>
        </form>
    );
}

/**
 * Configuration ▸ Tax rules: the tax-law figures every calculation uses, as dated
 * sets. Each set applies from its date until the next one, so a change in the law
 * is a new set (it starts as a copy of the one before) and nothing dated earlier
 * moves.
 */
export default function TaxRulesIndex({ sets }: { sets: RuleSet[] }) {
    const [selectedId, setSelectedId] = useStoredState(
        'tax-rules-set',
        sets[0]?.id ?? 0,
    );
    const selected = sets.find((s) => s.id === selectedId) ?? sets[0];
    const [addOpen, setAddOpen] = useState(false);
    const [addKey, setAddKey] = useState(0);

    return (
        <>
            <Head title="Tax rules" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-2xl font-semibold">Tax rules</h1>
                    <Button
                        className="ml-auto"
                        onClick={() => {
                            setAddKey((k) => k + 1);
                            setAddOpen(true);
                        }}
                        size="icon"
                        aria-label="Add rules from a date"
                        title="Add rules from a date"
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {sets.map((s) => (
                        <Button
                            key={s.id}
                            variant="outline"
                            size="sm"
                            aria-pressed={s.id === selected?.id}
                            className={cn(
                                s.id === selected?.id &&
                                    'text-primary border-primary',
                            )}
                            onClick={() => setSelectedId(s.id)}
                        >
                            From {formatDate(s.valid_from)}
                        </Button>
                    ))}
                </div>

                {selected && (
                    <RuleSetForm
                        key={selected.id}
                        set={selected}
                        canDelete={sets.length > 1}
                    />
                )}
            </div>

            <CrudFormDialog
                key={addKey}
                open={addOpen}
                onOpenChange={setAddOpen}
                singular="set of rules"
                baseUrl="/configuration/tax-rules"
                editing={null}
                fields={[
                    {
                        key: 'valid_from',
                        label: 'Applies from',
                        type: 'date',
                        required: true,
                    },
                    { key: 'note', label: 'Note (optional)', type: 'text' },
                ]}
                description="Starts as a copy of the rules in force before that date — then change what the law changed."
            />
        </>
    );
}

TaxRulesIndex.layout = {
    breadcrumbs: [{ title: 'Tax rules', href: '/configuration/tax-rules' }],
};

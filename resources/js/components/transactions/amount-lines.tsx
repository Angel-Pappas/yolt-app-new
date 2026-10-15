import { X } from 'lucide-react';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    type AmountLine,
    type AmountMode,
    defaultWithheldRateId,
    emptyAmountLine,
    lineCalc,
    type PayrollAmounts,
    parseAmount,
    round2,
    sumLines,
} from '@/lib/amounts';
import { formatAmount } from '@/lib/format';
import { cn } from '@/lib/utils';

/*
 * The amount area of an income/expense — shared by the transaction form and each
 * period of a recurring transaction, so the two always look and calculate the same.
 * Server-side twin: `App\Support\AmountLines` (kept in lockstep with `lineCalc`).
 */

// The amount maths live in `@/lib/amounts` (unit-tested); re-exported so the forms
// keep importing everything amount-related from this one module.
export {
    type AmountLine,
    type AmountMode,
    emptyAmountLine,
    emptyPayroll,
    lineCalc,
    linesPayload,
    normalizeAmount,
    optionalAmount,
    parseAmount,
    type PayrollAmounts,
    PAYROLL_CATEGORY,
    round2,
    sumLines,
} from '@/lib/amounts';

const NONE = 'none';

type EditorProps = {
    mode: AmountMode;
    onModeChange: (mode: AmountMode) => void;
    lines: AmountLine[];
    onLinesChange: (lines: AmountLine[]) => void;
    error?: string;
};

/**
 * The Net/Total toggle plus the amount lines: each `[amount][VAT][W][×]`, with a
 * withholding sub-line (read-only base, rate, withheld amount) under a line whose W
 * is on, and "+ Add VAT line" below.
 */
export function AmountLinesEditor({
    mode,
    onModeChange,
    lines,
    onLinesChange,
    error,
}: EditorProps) {
    const { vatRates, withheldRates } = useFinanceLookups();

    function setLine(i: number, patch: Partial<AmountLine>) {
        onLinesChange(
            lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)),
        );
    }

    // The per-line W button: turning it on seeds the 20% rate (kept if already set).
    function toggleWithheld(i: number) {
        const line = lines[i];
        setLine(
            i,
            line.withheld
                ? { withheld: false }
                : {
                      withheld: true,
                      withheld_rate_id:
                          line.withheld_rate_id ||
                          defaultWithheldRateId(withheldRates),
                  },
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Label>Amount</Label>
                <div className="inline-flex rounded-md border p-0.5 text-xs">
                    {(['net', 'total'] as const).map((m) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => onModeChange(m)}
                            className={cn(
                                'rounded px-2 py-1 capitalize',
                                mode === m && 'bg-muted font-medium',
                            )}
                        >
                            {m}
                        </button>
                    ))}
                </div>
            </div>

            {lines.map((line, i) => {
                const calc = lineCalc(line, mode, vatRates, withheldRates);
                return (
                    <div key={i} className="grid gap-1.5">
                        <div className="flex items-center gap-2">
                            <Input
                                inputMode="decimal"
                                value={line.amount}
                                onChange={(e) =>
                                    setLine(i, { amount: e.target.value })
                                }
                                placeholder={mode === 'total' ? 'Total' : 'Net'}
                                required
                                className="flex-1"
                            />
                            <Select
                                value={line.vat_rate_id || NONE}
                                onValueChange={(v) =>
                                    setLine(i, {
                                        vat_rate_id: v === NONE ? '' : v,
                                    })
                                }
                            >
                                <SelectTrigger className="w-28">
                                    <SelectValue placeholder="VAT" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>No VAT</SelectItem>
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
                            <Button
                                type="button"
                                variant={line.withheld ? 'default' : 'outline'}
                                size="icon"
                                onClick={() => toggleWithheld(i)}
                                disabled={withheldRates.length === 0}
                                aria-pressed={line.withheld}
                                aria-label="Toggle withholding tax"
                                title="Withholding tax"
                                className="shrink-0 font-semibold"
                            >
                                W
                            </Button>
                            {lines.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                        onLinesChange(
                                            lines.filter((_, idx) => idx !== i),
                                        )
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
                                    value={formatAmount(calc.net)}
                                    aria-label="Withholding base"
                                    className="bg-muted/50 text-muted-foreground flex-1"
                                />
                                <Select
                                    value={line.withheld_rate_id || NONE}
                                    onValueChange={(v) =>
                                        setLine(i, {
                                            withheld_rate_id:
                                                v === NONE ? '' : v,
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-28">
                                        <SelectValue placeholder="Rate" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <span className="w-[72px] shrink-0 text-right text-sm text-red-600 tabular-nums dark:text-red-500">
                                    −{formatAmount(calc.withheld)}
                                </span>
                            </div>
                        )}
                    </div>
                );
            })}
            <InputError message={error} />
            <button
                type="button"
                onClick={() => onLinesChange([...lines, emptyAmountLine()])}
                className="text-primary self-start text-sm"
            >
                + Add VAT line
            </button>
        </div>
    );
}

/** The Net / VAT / (Withheld) / Total box. Withheld shows only when a line has it. */
export function AmountSummary({
    lines,
    mode,
}: {
    lines: AmountLine[];
    mode: AmountMode;
}) {
    const { vatRates, withheldRates } = useFinanceLookups();
    const { net, vat, withheld, total } = sumLines(
        lines,
        mode,
        vatRates,
        withheldRates,
    );
    const hasWithheld = lines.some((l) => l.withheld);

    return (
        <div
            className={cn(
                'bg-muted/50 grid gap-2 rounded-lg p-3 text-sm tabular-nums',
                hasWithheld ? 'grid-cols-4' : 'grid-cols-3',
            )}
        >
            <div>
                <div className="text-muted-foreground text-xs">Net</div>
                {formatAmount(net)}
            </div>
            <div>
                <div className="text-muted-foreground text-xs">VAT</div>
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
                <div className="text-muted-foreground text-xs">Total</div>
                <span className="font-medium">{formatAmount(total)}</span>
            </div>
        </div>
    );
}

type PayrollFieldsProps = {
    value: PayrollAmounts;
    onChange: (patch: Partial<PayrollAmounts>) => void;
    /** Prefix for the inputs' ids, so several sets can share one page. */
    idPrefix?: string;
    errors?: Partial<Record<keyof PayrollAmounts, string>>;
};

/** The manual payroll inputs: Net amount · FMY · EFKA Employee · EFKA Employer. */
export function PayrollFields({
    value,
    onChange,
    idPrefix = '',
    errors = {},
}: PayrollFieldsProps) {
    const fields: { key: keyof PayrollAmounts; label: string }[] = [
        { key: 'net', label: 'Net amount' },
        { key: 'fmy', label: 'FMY' },
        { key: 'efka_employee', label: 'EFKA Employee' },
        { key: 'efka_employer', label: 'EFKA Employer' },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {fields.map(({ key, label }) => (
                <div key={key} className="grid gap-2">
                    <Label htmlFor={`${idPrefix}${key}`}>{label}</Label>
                    <Input
                        id={`${idPrefix}${key}`}
                        inputMode="decimal"
                        value={value[key]}
                        onChange={(e) => onChange({ [key]: e.target.value })}
                        required={key === 'net'}
                    />
                    <InputError message={errors[key]} />
                </div>
            ))}
        </div>
    );
}

/** Payroll figures (all manual): To Pay = Net − FMY − EFKA ee is the cash the
 *  employee actually receives (and what the wallet moves by); Total Cost =
 *  Net + EFKA er is informational — employer EFKA is a liability paid later. */
export function PayrollSummary({ value }: { value: PayrollAmounts }) {
    const net = parseAmount(value.net);
    const fmy = parseAmount(value.fmy);
    const efkaEe = parseAmount(value.efka_employee);
    const efkaEr = parseAmount(value.efka_employer);
    const toPay = round2(net - fmy - efkaEe);
    const totalCost = round2(net + efkaEr);

    return (
        <div className="bg-muted/50 grid grid-cols-3 gap-2 rounded-lg p-3 text-sm tabular-nums">
            <div>
                <div className="text-muted-foreground text-xs">Net</div>
                {formatAmount(net)}
            </div>
            <div>
                <div className="text-muted-foreground text-xs">FMY</div>
                <span className="text-red-600 dark:text-red-500">
                    −{formatAmount(fmy)}
                </span>
            </div>
            <div>
                <div className="text-muted-foreground text-xs">EFKA ee</div>
                <span className="text-red-600 dark:text-red-500">
                    −{formatAmount(efkaEe)}
                </span>
            </div>
            <div>
                <div className="text-muted-foreground text-xs">To Pay</div>
                <span className="font-medium">{formatAmount(toPay)}</span>
            </div>
            <div>
                <div className="text-muted-foreground text-xs">EFKA er</div>
                {formatAmount(efkaEr)}
            </div>
            <div>
                <div className="text-muted-foreground text-xs">Total Cost</div>
                <span className="font-medium">{formatAmount(totalCost)}</span>
            </div>
        </div>
    );
}

import type { RateOption } from '@/components/transactions/lookups';
import { formatAmount } from '@/lib/format';

/*
 * The pure maths of a transaction's amount area — no React, so it is unit-tested
 * (`amounts.test.ts`). Used by `components/transactions/amount-lines.tsx` (the
 * shared editor of the transaction and recurrence forms). Server-side twin:
 * `App\Support\AmountLines` — keep `lineCalc` in lockstep with it.
 */

/** The exact category name that turns the form into a payroll entry. */
export const PAYROLL_CATEGORY = 'Payroll';

export type AmountMode = 'net' | 'total';

/** One amount line: the typed amount, its VAT rate, and the per-line W toggle. */
export type AmountLine = {
    amount: string;
    vat_rate_id: string;
    withheld: boolean;
    withheld_rate_id: string;
};

/** The manual payroll figures (as typed). */
export type PayrollAmounts = {
    net: string;
    fmy: string;
    efka_employee: string;
    efka_employer: string;
};

export function emptyAmountLine(): AmountLine {
    return {
        amount: '',
        vat_rate_id: '',
        withheld: false,
        withheld_rate_id: '',
    };
}

export function emptyPayroll(): PayrollAmounts {
    return { net: '', fmy: '', efka_employee: '', efka_employer: '' };
}

/**
 * Read a typed amount as a number, or NaN when it isn't one. Accepts a comma or a
 * dot as the decimal, and Greek thousands dots when a decimal comma is present
 * (`1.234,56`) or there are several dot groups (`1.234.567`). A lone dot is the
 * decimal (`1.5`), so plain typing never changes meaning.
 */
export function readAmount(value: string): number {
    const s = String(value).replace(/\s/g, '');
    if (s === '') return NaN;
    let plain: string;
    if (s.includes(',')) {
        plain = s.replace(/\./g, '').replace(',', '.');
    } else if (/^-?\d{1,3}(\.\d{3}){2,}$/.test(s)) {
        plain = s.replace(/\./g, '');
    } else {
        plain = s;
    }
    return /^-?(\d+\.?\d*|\.\d+)$/.test(plain) ? Number(plain) : NaN;
}

/** A typed amount as a number for live maths (0 when blank or not a number). */
export function parseAmount(value: string): number {
    const n = readAmount(value);
    return Number.isFinite(n) ? n : 0;
}

export function round2(n: number): number {
    return Math.round(n * 100) / 100;
}

/** A typed amount normalised for submit (`1.234,56` → `1234.56`); anything that
 *  isn't a number is sent as typed so the server's validation reports it. */
export function normalizeAmount(value: string): string {
    const n = readAmount(value);
    return Number.isFinite(n) ? String(n) : String(value).trim();
}

/** How an amount field shows its value while not being edited: `1.234,56`. */
export function displayAmount(value: string): string {
    const n = readAmount(value);
    return Number.isFinite(n) ? formatAmount(n) : value;
}

/** How an amount field shows its value while being edited: plain, no grouping,
 *  decimal comma (`1234,56`), so typing never fights the thousands dots. */
export function editableAmount(value: string): string {
    const n = readAmount(value);
    return Number.isFinite(n) ? String(n).replace('.', ',') : value;
}

/** A payroll amount for submit: normalised, or null when blank. */
export function optionalAmount(value: string): string | null {
    return value ? normalizeAmount(value) : null;
}

/** The percentage of the rate a select id points at, or 0 when unset/unknown. */
export function ratePct(rates: RateOption[], id: string): number {
    const rate = rates.find((r) => String(r.id) === id);
    return rate ? Number(rate.rate) : 0;
}

/** The withholding rate a freshly-toggled line gets: the 20% one (the usual Greek
 *  contractor rate) when it exists, else the first available rate. */
export function defaultWithheldRateId(withheldRates: RateOption[]): string {
    const twenty = withheldRates.find((r) => Number(r.rate) === 20);
    return twenty
        ? String(twenty.id)
        : withheldRates[0]
          ? String(withheldRates[0].id)
          : '';
}

/** Net / VAT / withheld for one line, interpreted by the single Net/Total mode.
 *  Total mode reverses the net out of the cash total (net + VAT − withheld) and
 *  anchors VAT so the line reconstructs to the exact typed total. */
export function lineCalc(
    line: AmountLine,
    mode: AmountMode,
    vatRates: RateOption[],
    withheldRates: RateOption[],
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

/** Net / VAT / withheld / cash total summed across the lines. */
export function sumLines(
    lines: AmountLine[],
    mode: AmountMode,
    vatRates: RateOption[],
    withheldRates: RateOption[],
): { net: number; vat: number; withheld: number; total: number } {
    let net = 0;
    let vat = 0;
    let withheld = 0;
    for (const line of lines) {
        const c = lineCalc(line, mode, vatRates, withheldRates);
        net += c.net;
        vat += c.vat;
        withheld += c.withheld;
    }
    net = round2(net);
    vat = round2(vat);
    withheld = round2(withheld);
    return { net, vat, withheld, total: round2(net + vat - withheld) };
}

/** The lines as the server expects them: withholding only when the W is on. */
export function linesPayload(lines: AmountLine[]) {
    return lines.map((l) => ({
        amount: normalizeAmount(l.amount),
        vat_rate_id: l.vat_rate_id || null,
        withheld_rate_id:
            l.withheld && l.withheld_rate_id ? l.withheld_rate_id : null,
    }));
}

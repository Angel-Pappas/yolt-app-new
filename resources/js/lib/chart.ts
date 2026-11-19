/*
 * Pure helpers for the cash-flow chart — no React, so they are unit-tested
 * (`chart.test.ts`).
 */

/**
 * "Nice" axis ticks covering [min, max]: evenly spaced round numbers (1, 2 or 5
 * × a power of ten), always including 0 when the range crosses it. Returns the
 * ticks in ascending order; the first and last bound the data.
 */
export function niceTicks(min: number, max: number, target = 5): number[] {
    if (!Number.isFinite(min) || !Number.isFinite(max)) return [0, 1];
    if (min > 0) min = 0;
    if (max < 0) max = 0;
    if (min === max) max = min + 1;

    const rough = (max - min) / Math.max(1, target);
    const power = Math.pow(10, Math.floor(Math.log10(rough)));
    const fraction = rough / power;
    const step =
        (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10) *
        power;

    const first = Math.floor(min / step) * step;
    const last = Math.ceil(max / step) * step;
    const ticks: number[] = [];
    for (let v = first; v <= last + step / 2; v += step) {
        // Avoid -0 and float dust (0.30000000000000004).
        ticks.push(Math.round(v / step) * step || 0);
    }
    return ticks;
}

/** A compact euro label for an axis: 1500 → "1,5k", 25000 → "25k", 1200000 → "1,2M". */
export function compactAmount(value: number): string {
    const abs = Math.abs(value);
    const sign = value < 0 ? '−' : '';
    const trim = (n: number) =>
        String(Math.round(n * 10) / 10).replace('.', ',');
    if (abs >= 1_000_000) return `${sign}${trim(abs / 1_000_000)}M`;
    if (abs >= 1_000) return `${sign}${trim(abs / 1_000)}k`;
    return `${sign}${trim(abs)}`;
}

const SHORT_MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** 'YYYY-MM' → "Nov 26". */
export function shortMonth(key: string): string {
    const [year, month] = key.split('-');
    return `${SHORT_MONTHS[Number(month) - 1] ?? month} ${year.slice(2)}`;
}

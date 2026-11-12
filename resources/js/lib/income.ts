/** The "when is it due" buttons on Finance ▸ Income. */
export type DuePreset = 'all' | 'overdue' | 'this-month' | 'next-month';

export const DUE_PRESETS: { key: DuePreset; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'this-month', label: 'This month' },
    { key: 'next-month', label: 'Next month' },
];

/** 'YYYY-MM' of the month `offset` months after the one `iso` falls in. */
export function monthKey(iso: string, offset = 0): string {
    const year = Number(iso.slice(0, 4));
    const month = Number(iso.slice(5, 7)) - 1 + offset;
    const y = year + Math.floor(month / 12);
    const m = ((month % 12) + 12) % 12;
    return `${y}-${String(m + 1).padStart(2, '0')}`;
}

/**
 * Whether a payment date (ISO) falls under a due preset, seen from `today` (ISO):
 * overdue = before today; this/next month = in that calendar month.
 */
export function isDue(date: string, preset: DuePreset, today: string): boolean {
    switch (preset) {
        case 'all':
            return true;
        case 'overdue':
            return date < today;
        case 'this-month':
            return date.slice(0, 7) === monthKey(today);
        case 'next-month':
            return date.slice(0, 7) === monthKey(today, 1);
    }
}

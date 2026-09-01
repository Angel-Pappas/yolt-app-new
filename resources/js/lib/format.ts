// Greek-style display formatting: dates as dd/mm/yyyy, amounts with "." as the
// thousands separator and "," as the decimal, always two decimals.

const amountFormatter = new Intl.NumberFormat('el-GR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Format a numeric amount (money `numeric` columns arrive as strings). */
export function formatAmount(value: string | number): string {
    const n = typeof value === 'string' ? Number(value) : value;
    return amountFormatter.format(Number.isFinite(n) ? n : 0);
}

/** Format an ISO date (or datetime) as dd/mm/yyyy. */
export function formatDate(value: string): string {
    const [year, month, day] = value.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
}

/** ISO `yyyy-mm-dd` → display `dd/mm/yyyy` (empty in → empty out). */
export function isoToDisplay(iso: string): string {
    if (!iso) return '';
    const [year, month, day] = iso.slice(0, 10).split('-');
    if (!year || !month || !day) return '';
    return `${day}/${month}/${year}`;
}

/**
 * Strict display `dd/mm/yyyy` → ISO `yyyy-mm-dd`. Returns '' for anything that
 * isn't a real calendar date (rejects 31/02 etc. by round-tripping through Date),
 * so a bad value becomes empty rather than a plausible lie.
 */
export function displayToIso(display: string): string {
    const m = display.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!m) return '';
    const day = Number(m[1]);
    const month = Number(m[2]);
    const year = Number(m[3]);
    const date = new Date(year, month - 1, day);
    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${year}-${pad(month)}-${pad(day)}`;
}

/** Group a phone number's digits for display, e.g. "2101234567" → "210 123 4567". */
export function formatPhone(value: string | null | undefined): string {
    if (!value) return '';
    const digits = value.replace(/\D/g, '');
    if (digits.length < 7) return digits;
    return digits.replace(/(\d{3})(\d{3})(\d+)/, '$1 $2 $3');
}

const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/**
 * Format a "yyyy-mm" tax-period key as e.g. "July 2026". English month names by
 * design — the UI text is English even though dates/amounts are Greek-style.
 */
export function formatMonthYear(key: string): string {
    const [year, month] = key.split('-');
    const name = MONTH_NAMES[Number(month) - 1] ?? month;
    return `${name} ${year}`;
}

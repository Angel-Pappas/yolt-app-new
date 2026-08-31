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

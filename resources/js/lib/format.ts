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

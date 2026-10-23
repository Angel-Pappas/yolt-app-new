/** The Recurring page's groups, in display order (server: `RecurringOverview`). */
export const RECURRING_GROUPS = [
    { key: 'payroll', label: 'Payroll' },
    { key: 'contractors', label: 'Contractors' },
    { key: 'expenses', label: 'Expenses' },
    { key: 'income', label: 'Income' },
] as const;

export type RecurringGroup = (typeof RECURRING_GROUPS)[number]['key'];

/** How many rows fall in each group. */
export function groupCounts(
    rows: { group: string }[],
): Record<RecurringGroup, number> {
    const counts = { payroll: 0, contractors: 0, expenses: 0, income: 0 };
    for (const row of rows) {
        if (row.group in counts) counts[row.group as RecurringGroup]++;
    }
    return counts;
}

/** The group the page opens on: the first one that has rows (else the first). */
export function defaultGroup(rows: { group: string }[]): RecurringGroup {
    const counts = groupCounts(rows);
    return (
        RECURRING_GROUPS.find((g) => counts[g.key] > 0)?.key ??
        RECURRING_GROUPS[0].key
    );
}

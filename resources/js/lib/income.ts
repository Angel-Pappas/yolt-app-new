/** 'YYYY-MM' of the month `offset` months after the one `iso` falls in. */
export function monthKey(iso: string, offset = 0): string {
    const year = Number(iso.slice(0, 4));
    const month = Number(iso.slice(5, 7)) - 1 + offset;
    const y = year + Math.floor(month / 12);
    const m = ((month % 12) + 12) % 12;
    return `${y}-${String(m + 1).padStart(2, '0')}`;
}

/**
 * Whether an unpaid payment line needs chasing: its payment date is in a month
 * before the current one (Angel: mark those with "!" — contact the customer, move
 * the date to this month or later).
 */
export function isFromPastMonth(date: string, today: string): boolean {
    return date.slice(0, 7) < monthKey(today);
}

/**
 * Payment lines grouped by customer (the Income tab's "By customer" view): each
 * customer with its lines (in the order given) and what it still owes, customers
 * by name.
 */
export function byCustomer<
    T extends { entity_id: number | null; entity: string | null; open: number },
>(rows: T[]): { id: number; name: string; owed: number; rows: T[] }[] {
    const groups = new Map<
        number,
        { id: number; name: string; owed: number; rows: T[] }
    >();
    for (const r of rows) {
        const id = r.entity_id ?? 0;
        const g = groups.get(id) ?? {
            id,
            name: r.entity ?? '—',
            owed: 0,
            rows: [],
        };
        g.rows.push(r);
        g.owed = Math.round((g.owed + r.open) * 100) / 100;
        groups.set(id, g);
    }
    return [...groups.values()].sort((a, b) => a.name.localeCompare(b.name));
}

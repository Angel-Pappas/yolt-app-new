/**
 * Pure helpers of the Bank import page (the bank and the app side by side).
 */

/** What the page shows of an app row. */
export type AppRow = {
    id: number;
    date: string;
    type: string;
    to_wallet_id: number | null;
    entity: string | null;
    category: string | null;
    description: string;
    /** The cash it moves, positive. */
    amount: number;
    is_reconciled: boolean;
};

/** "Κώνστας Γιώργος · Contractors" — who and what a row is. */
export function whoAndWhat(t: {
    type: string;
    entity: string | null;
    category: string | null;
}): string {
    const who = t.type === 'transfer' ? 'Transfer' : (t.entity ?? 'No entity');
    return t.category ? `${who} · ${t.category}` : who;
}

/** The row's cash as the wallet sees it: money in positive, money out negative. */
export function signedCash(t: AppRow, walletId: number): number {
    const incoming =
        t.type === 'income' ||
        (t.type === 'transfer' && t.to_wallet_id === walletId);
    return incoming ? t.amount : -t.amount;
}

/**
 * The app's unpaired rows a bank line could be paired with by hand: those moving
 * money the same way, the closest in amount first, then the closest in date.
 */
export function pairOptions(
    line: { date: string; amount: number },
    rows: AppRow[],
    walletId: number,
    limit = 8,
): AppRow[] {
    const day = (iso: string) => Date.parse(`${iso}T00:00:00Z`) / 86_400_000;
    return rows
        .filter((t) => signedCash(t, walletId) > 0 === line.amount > 0)
        .map((t) => ({
            t,
            gap: Math.abs(t.amount - Math.abs(line.amount)),
            days: Math.abs(day(t.date) - day(line.date)),
        }))
        .sort((a, b) => a.gap - b.gap || a.days - b.days || a.t.id - b.t.id)
        .slice(0, limit)
        .map((x) => x.t);
}

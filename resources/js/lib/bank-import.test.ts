import { describe, expect, it } from 'vite-plus/test';
import {
    type AppRow,
    nextMonth,
    pairOptions,
    signedCash,
    stillNeeded,
    whoAndWhat,
} from './bank-import';

const row = (over: Partial<AppRow>): AppRow => ({
    id: 1,
    date: '2026-10-05',
    type: 'expense',
    to_wallet_id: null,
    entity: 'Meta',
    category: 'Advertising',
    description: '',
    amount: 10,
    is_reconciled: false,
    ...over,
});

describe('whoAndWhat', () => {
    it('names the entity and category', () => {
        expect(whoAndWhat(row({}))).toBe('Meta · Advertising');
        expect(whoAndWhat(row({ category: null }))).toBe('Meta');
        expect(whoAndWhat(row({ entity: null, category: null }))).toBe(
            'No entity',
        );
        expect(whoAndWhat(row({ type: 'transfer', category: null }))).toBe(
            'Transfer',
        );
    });
});

describe('signedCash', () => {
    it('is positive for money coming into the wallet', () => {
        expect(signedCash(row({ type: 'income' }), 3)).toBe(10);
        expect(signedCash(row({ type: 'expense' }), 3)).toBe(-10);
        expect(signedCash(row({ type: 'transfer', to_wallet_id: 3 }), 3)).toBe(
            10,
        );
        expect(signedCash(row({ type: 'transfer', to_wallet_id: 4 }), 3)).toBe(
            -10,
        );
    });
});

describe('pairOptions', () => {
    it('keeps rows going the same way, closest amount then date first', () => {
        const rows = [
            row({ id: 1, amount: 50 }),
            row({ id: 2, amount: 12, date: '2026-10-20' }),
            row({ id: 3, amount: 12, date: '2026-10-06' }),
            row({ id: 4, type: 'income', amount: 12 }),
        ];
        const ids = pairOptions(
            { date: '2026-10-05', amount: -12 },
            rows,
            3,
        ).map((t) => t.id);
        expect(ids).toEqual([3, 2, 1]);
        expect(
            pairOptions({ date: '2026-10-05', amount: 12 }, rows, 3).map(
                (t) => t.id,
            ),
        ).toEqual([4]);
    });

    it('stops at the limit', () => {
        const rows = [1, 2, 3].map((id) => row({ id }));
        expect(
            pairOptions({ date: '2026-10-05', amount: -10 }, rows, 3, 2),
        ).toHaveLength(2);
    });
});

describe('nextMonth', () => {
    it('keeps the day, clamped to a short month, across years', () => {
        expect(nextMonth('2026-11-15')).toBe('2026-12-15');
        expect(nextMonth('2026-01-31')).toBe('2026-02-28');
        expect(nextMonth('2026-12-10')).toBe('2027-01-10');
    });
});

describe('stillNeeded', () => {
    it('is what the line lacks, whatever its sign', () => {
        expect(stillNeeded(-6200, 5000)).toBe(1200);
        expect(stillNeeded(7400, 7400)).toBe(0);
    });
});

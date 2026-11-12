import { describe, expect, it } from 'vite-plus/test';
import { isDue, monthKey } from './income';

describe('monthKey', () => {
    it('gives the month an ISO date falls in', () => {
        expect(monthKey('2026-11-12')).toBe('2026-11');
    });

    it('rolls over the year end', () => {
        expect(monthKey('2026-12-31', 1)).toBe('2027-01');
        expect(monthKey('2026-01-15', -1)).toBe('2025-12');
    });
});

describe('isDue', () => {
    const today = '2026-11-12';

    it('all takes everything', () => {
        expect(isDue('2020-01-01', 'all', today)).toBe(true);
    });

    it('overdue is strictly before today', () => {
        expect(isDue('2026-11-11', 'overdue', today)).toBe(true);
        expect(isDue('2026-11-12', 'overdue', today)).toBe(false);
    });

    it('this month and next month go by calendar month', () => {
        expect(isDue('2026-11-01', 'this-month', today)).toBe(true);
        expect(isDue('2026-12-01', 'this-month', today)).toBe(false);
        expect(isDue('2026-12-31', 'next-month', today)).toBe(true);
        expect(isDue('2027-01-01', 'next-month', today)).toBe(false);
    });
});

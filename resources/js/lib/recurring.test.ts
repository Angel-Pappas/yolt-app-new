import { describe, expect, it } from 'vite-plus/test';
import { defaultGroup, groupCounts } from './recurring';

describe('groupCounts', () => {
    it('counts the rows in each group', () => {
        expect(
            groupCounts([
                { group: 'payroll' },
                { group: 'expenses' },
                { group: 'expenses' },
            ]),
        ).toEqual({ payroll: 1, contractors: 0, expenses: 2, income: 0 });
    });
});

describe('defaultGroup', () => {
    it('opens on the first group that has rows', () => {
        expect(defaultGroup([{ group: 'income' }, { group: 'expenses' }])).toBe(
            'expenses',
        );
    });

    it('falls back to Payroll when there are no rows', () => {
        expect(defaultGroup([])).toBe('payroll');
    });
});

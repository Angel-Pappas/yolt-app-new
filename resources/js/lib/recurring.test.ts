import { describe, expect, it } from 'vite-plus/test';
import {
    defaultGroup,
    groupCounts,
    paysOnLastWorkingDay,
    recurrenceDefaults,
} from './recurring';

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

describe('recurrenceDefaults', () => {
    it('makes a customer recurrence income', () => {
        expect(recurrenceDefaults('customer')).toEqual({
            type: 'income',
            payroll: false,
        });
    });

    it('makes an employee recurrence a payroll expense', () => {
        expect(recurrenceDefaults('employee')).toEqual({
            type: 'expense',
            payroll: true,
        });
    });

    it('makes any other recurrence a plain expense', () => {
        for (const type of [
            'supplier',
            'contractor',
            'shareholder',
            undefined,
        ]) {
            expect(recurrenceDefaults(type)).toEqual({
                type: 'expense',
                payroll: false,
            });
        }
    });
});

describe('paysOnLastWorkingDay', () => {
    it('pays contractor and employee expenses at the month end', () => {
        expect(paysOnLastWorkingDay('expense', 'contractor')).toBe(true);
        expect(paysOnLastWorkingDay('expense', 'employee')).toBe(true);
    });

    it('pays everything else on its own day', () => {
        expect(paysOnLastWorkingDay('expense', 'supplier')).toBe(false);
        expect(paysOnLastWorkingDay('income', 'contractor')).toBe(false);
        expect(paysOnLastWorkingDay('expense', undefined)).toBe(false);
    });
});

import { describe, expect, it } from 'vite-plus/test';
import {
    displayToIso,
    formatAmount,
    formatDate,
    formatMonthYear,
    isoToDisplay,
} from './format';

describe('formatAmount', () => {
    it('uses Greek separators and always two decimals', () => {
        expect(formatAmount(1234.5)).toBe('1.234,50');
        expect(formatAmount('0')).toBe('0,00');
        expect(formatAmount('-12.345')).toBe('-12,35');
    });

    it('shows junk as 0,00 rather than NaN', () => {
        expect(formatAmount('abc')).toBe('0,00');
    });
});

describe('dates', () => {
    it('formats ISO dates and datetimes as dd/mm/yyyy', () => {
        expect(formatDate('2026-09-03')).toBe('03/09/2026');
        expect(formatDate('2026-09-03T10:00:00Z')).toBe('03/09/2026');
        expect(isoToDisplay('2026-01-31')).toBe('31/01/2026');
        expect(isoToDisplay('')).toBe('');
    });

    it('parses dd/mm/yyyy strictly, rejecting impossible dates', () => {
        expect(displayToIso('3/9/2026')).toBe('2026-09-03');
        expect(displayToIso('29/02/2028')).toBe('2028-02-29');
        expect(displayToIso('29/02/2026')).toBe('');
        expect(displayToIso('31/04/2026')).toBe('');
        expect(displayToIso('2026-09-03')).toBe('');
    });

    it('names tax-period months in English', () => {
        expect(formatMonthYear('2026-07')).toBe('July 2026');
    });
});

import { describe, expect, it } from 'vite-plus/test';
import { compactAmount, niceTicks, shortMonth } from './chart';

describe('niceTicks', () => {
    it('covers the data with round steps from zero', () => {
        expect(niceTicks(0, 9300)).toEqual([0, 2000, 4000, 6000, 8000, 10000]);
    });

    it('includes zero when everything is positive or negative', () => {
        expect(niceTicks(4000, 9000)[0]).toBe(0);
        const negative = niceTicks(-9000, -4000);
        expect(negative[negative.length - 1]).toBe(0);
    });

    it('spans below zero for a negative balance', () => {
        const ticks = niceTicks(-3200, 12000);
        expect(ticks[0]).toBeLessThanOrEqual(-3200);
        expect(ticks[ticks.length - 1]).toBeGreaterThanOrEqual(12000);
        expect(ticks).toContain(0);
    });

    it('survives a flat or empty range', () => {
        expect(niceTicks(0, 0).length).toBeGreaterThan(1);
        expect(niceTicks(NaN, NaN)).toEqual([0, 1]);
    });
});

describe('compactAmount', () => {
    it('shortens thousands and millions with a decimal comma', () => {
        expect(compactAmount(1500)).toBe('1,5k');
        expect(compactAmount(25000)).toBe('25k');
        expect(compactAmount(1200000)).toBe('1,2M');
        expect(compactAmount(-4000)).toBe('−4k');
        expect(compactAmount(0)).toBe('0');
    });
});

describe('shortMonth', () => {
    it('turns a month key into a short label', () => {
        expect(shortMonth('2026-11')).toBe('Nov 26');
        expect(shortMonth('2027-01')).toBe('Jan 27');
    });
});

import { describe, expect, it } from 'vite-plus/test';
import type { RateOption } from '@/components/transactions/lookups';
import {
    type AmountLine,
    defaultWithheldRateId,
    lineCalc,
    linesPayload,
    parseAmount,
    round2,
    sumLines,
} from './amounts';

const vat: RateOption[] = [
    { id: 1, name: '24%', rate: '24.00' },
    { id: 2, name: '6%', rate: '6.00' },
    { id: 3, name: '0%', rate: '0.00' },
];
const withheld: RateOption[] = [
    { id: 7, name: '8%', rate: '8.00' },
    { id: 9, name: '20%', rate: '20.00' },
];

function line(amount: string, vatId = '1', withheldId?: string): AmountLine {
    return {
        amount,
        vat_rate_id: vatId,
        withheld: withheldId !== undefined,
        withheld_rate_id: withheldId ?? '',
    };
}

describe('parseAmount', () => {
    it('reads dot or comma decimals and treats junk as 0', () => {
        expect(parseAmount('12.5')).toBe(12.5);
        expect(parseAmount('12,5')).toBe(12.5);
        expect(parseAmount('')).toBe(0);
        expect(parseAmount('abc')).toBe(0);
    });

    it('does not accept thousands separators (type 1234,56, not 1.234,56)', () => {
        expect(parseAmount('1.234,56')).toBe(0);
    });
});

describe('round2', () => {
    it('rounds to cents', () => {
        expect(round2(2.345)).toBe(2.35);
        expect(round2(10)).toBe(10);
    });
});

describe('lineCalc — Net mode', () => {
    it('adds VAT on top of the typed net', () => {
        expect(lineCalc(line('100'), 'net', vat, withheld)).toEqual({
            net: 100,
            vat: 24,
            withheld: 0,
        });
    });

    it('withholds a share of the net when W is on', () => {
        expect(lineCalc(line('100', '1', '9'), 'net', vat, withheld)).toEqual({
            net: 100,
            vat: 24,
            withheld: 20,
        });
    });

    it('treats an unknown or empty rate as 0%', () => {
        expect(lineCalc(line('50', ''), 'net', vat, withheld)).toEqual({
            net: 50,
            vat: 0,
            withheld: 0,
        });
    });
});

describe('lineCalc — Total mode', () => {
    it('backs VAT out of a typed total', () => {
        expect(lineCalc(line('124'), 'total', vat, withheld)).toEqual({
            net: 100,
            vat: 24,
            withheld: 0,
        });
    });

    it('reconstructs the exact typed cash total, even with withholding', () => {
        for (const typed of ['120', '99.99', '1234.56', '0.01', '777,77']) {
            const c = lineCalc(line(typed, '1', '9'), 'total', vat, withheld);
            expect(round2(c.net + c.vat - c.withheld)).toBe(parseAmount(typed));
        }
    });

    it('matches the worked example: 120 total @24% VAT, 20% withheld', () => {
        expect(lineCalc(line('120', '1', '9'), 'total', vat, withheld)).toEqual(
            {
                net: 115.38,
                vat: 27.7,
                withheld: 23.08,
            },
        );
    });
});

describe('sumLines', () => {
    it('sums several lines and gives the cash total', () => {
        const lines = [
            line('1000', '1'),
            line('200', '2'),
            line('100', '3', '9'),
        ];
        expect(sumLines(lines, 'net', vat, withheld)).toEqual({
            net: 1300,
            vat: 252,
            withheld: 20,
            total: 1532,
        });
    });

    it('is all zeros for no lines', () => {
        expect(sumLines([], 'net', vat, withheld)).toEqual({
            net: 0,
            vat: 0,
            withheld: 0,
            total: 0,
        });
    });
});

describe('linesPayload', () => {
    it('normalises the comma and sends withholding only when W is on', () => {
        const off = { ...line('10,5', '1'), withheld_rate_id: '9' };
        expect(linesPayload([off, line('3', '', '9')])).toEqual([
            { amount: '10.5', vat_rate_id: '1', withheld_rate_id: null },
            { amount: '3', vat_rate_id: null, withheld_rate_id: '9' },
        ]);
    });
});

describe('defaultWithheldRateId', () => {
    it('prefers the 20% rate, else the first, else none', () => {
        expect(defaultWithheldRateId(withheld)).toBe('9');
        expect(defaultWithheldRateId([withheld[0]])).toBe('7');
        expect(defaultWithheldRateId([])).toBe('');
    });
});

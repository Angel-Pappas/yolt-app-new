import { describe, expect, it } from 'vite-plus/test';
import { byCustomer, isFromPastMonth, monthKey } from './income';

describe('monthKey', () => {
    it('steps across years both ways', () => {
        expect(monthKey('2026-11-19')).toBe('2026-11');
        expect(monthKey('2026-11-19', 2)).toBe('2027-01');
        expect(monthKey('2026-01-05', -1)).toBe('2025-12');
    });
});

describe('isFromPastMonth', () => {
    it('marks only lines from an earlier month, not earlier days of this one', () => {
        expect(isFromPastMonth('2026-10-31', '2026-11-19')).toBe(true);
        expect(isFromPastMonth('2026-11-01', '2026-11-19')).toBe(false);
        expect(isFromPastMonth('2026-12-01', '2026-11-19')).toBe(false);
        expect(isFromPastMonth('2025-12-15', '2026-01-02')).toBe(true);
    });
});

describe('byCustomer', () => {
    it('groups lines per customer by name and adds up what is owed', () => {
        const groups = byCustomer([
            { entity_id: 2, entity: 'Zeta', open: 100, k: 'a' },
            { entity_id: 1, entity: 'Alpha', open: 10.1, k: 'b' },
            { entity_id: 2, entity: 'Zeta', open: 0, k: 'c' },
            { entity_id: 1, entity: 'Alpha', open: 0.2, k: 'd' },
        ]);
        expect(groups.map((g) => g.name)).toEqual(['Alpha', 'Zeta']);
        expect(groups[0].owed).toBe(10.3);
        expect(groups[1].rows.map((r) => r.k)).toEqual(['a', 'c']);
    });
});

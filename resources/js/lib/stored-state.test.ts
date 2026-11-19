import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { readStored, writeStored } from './stored-state';

// A minimal stand-in for the browser's sessionStorage (the tests run without a DOM).
const items = new Map<string, string>();
(globalThis as { window?: unknown }).window = {
    sessionStorage: {
        getItem: (k: string) => items.get(k) ?? null,
        setItem: (k: string, v: string) => void items.set(k, v),
        clear: () => items.clear(),
    },
};

describe('stored view state', () => {
    beforeEach(() => items.clear());

    it('gives the fallback when nothing is stored', () => {
        expect(readStored('list', { showAll: false })).toEqual({
            showAll: false,
        });
    });

    it('reads back what was written', () => {
        writeStored('list', {
            showAll: true,
            sort: [{ id: 'date', desc: true }],
        });
        expect(readStored('list', null)).toEqual({
            showAll: true,
            sort: [{ id: 'date', desc: true }],
        });
    });

    it('keeps keys apart', () => {
        writeStored('a', 1);
        writeStored('b', 2);
        expect(readStored('a', 0)).toBe(1);
        expect(readStored('b', 0)).toBe(2);
    });

    it('falls back on unreadable content', () => {
        items.set('yolt:view:broken', '{not json');
        expect(readStored('broken', 'fallback')).toBe('fallback');
    });
});

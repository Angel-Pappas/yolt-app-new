import { describe, expect, it } from 'vite-plus/test';
import { edgeScrollSpeed } from './drag-scroll';

describe('edgeScrollSpeed', () => {
    it('stays still in the middle of the area', () => {
        expect(edgeScrollSpeed(500, 100, 900)).toBe(0);
        expect(edgeScrollSpeed(180, 100, 900)).toBe(0);
        expect(edgeScrollSpeed(820, 100, 900)).toBe(0);
    });

    it('scrolls up near the top and down near the bottom, faster closer to the edge', () => {
        expect(edgeScrollSpeed(140, 100, 900)).toBe(-12);
        expect(edgeScrollSpeed(101, 100, 900)).toBe(-24);
        expect(edgeScrollSpeed(860, 100, 900)).toBe(12);
        expect(edgeScrollSpeed(899, 100, 900)).toBe(24);
    });

    it('goes full speed past the edges', () => {
        expect(edgeScrollSpeed(20, 100, 900)).toBe(-24);
        expect(edgeScrollSpeed(1000, 100, 900)).toBe(24);
    });

    it('keeps a still middle in a short area', () => {
        expect(edgeScrollSpeed(150, 100, 250)).toBe(0);
        expect(edgeScrollSpeed(101, 100, 250)).toBeLessThan(0);
        expect(edgeScrollSpeed(249, 100, 250)).toBeGreaterThan(0);
        expect(edgeScrollSpeed(100, 100, 100)).toBe(0);
    });
});

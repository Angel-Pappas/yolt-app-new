import { describe, expect, it } from 'vite-plus/test';
import { includesNormalized } from './search';

describe('includesNormalized', () => {
    it('ignores case, accents/tones and the Greek final sigma', () => {
        expect(includesNormalized('Γεωργαλάς Κωνσταντίνος', 'γεωργαλασ')).toBe(
            true,
        );
        expect(includesNormalized('Σύλλογος', 'συλλογοσ')).toBe(true);
        expect(includesNormalized('One Password', 'PASS')).toBe(true);
    });

    it('matches a run of letters, not a scattered subsequence', () => {
        expect(includesNormalized('σύλλογος λογοθεραπευτών', 'σουν')).toBe(
            false,
        );
    });
});

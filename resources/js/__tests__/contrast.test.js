import {describe, expect, it} from 'vitest';
import {contrastRatio, contrastWarnings, describeWarning} from '../theme/contrast.js';

describe('contrastRatio', () => {
    it('is 21 for black on white and symmetric', () => {
        expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 5);
        expect(contrastRatio('#ffffff', '#000000')).toBeCloseTo(21, 5);
    });

    it('is 1 for identical colours', () => {
        expect(contrastRatio('#336699', '#336699')).toBeCloseTo(1, 5);
    });

    it('matches known pairs', () => {
        expect(contrastRatio('#777777', '#ffffff')).toBeCloseTo(4.48, 1);
        expect(contrastRatio('#767676', '#ffffff')).toBeCloseTo(4.54, 1);
    });

    it('returns null for invalid input', () => {
        expect(contrastRatio('#fff', '#000000')).toBeNull();
        expect(contrastRatio('#12', '#000000')).toBeNull();
        expect(contrastRatio('nope', '#000000')).toBeNull();
        expect(contrastRatio(undefined, '#000000')).toBeNull();
    });
});

const ok = {text: '#000000', background: '#ffffff', surface: '#ffffff'};

describe('contrastWarnings', () => {
    it('is empty for passing themes', () => {
        expect(contrastWarnings({light: ok, dark: ok})).toEqual([]);
    });

    it('flags failing pairs per scheme', () => {
        const bad = {text: '#777777', background: '#ffffff', surface: '#888888'};
        const warnings = contrastWarnings({light: bad, dark: ok});
        expect(warnings.map((w) => `${w.scheme} ${w.pair}`)).toEqual(['light text/background', 'light text/surface']);
        expect(warnings[0].ratio).toBeLessThan(4.5);
    });

    it('passes at the 4.5 boundary', () => {
        expect(contrastWarnings({light: {...ok, text: '#767676'}, dark: ok})).toEqual([]);
        expect(contrastWarnings({light: {...ok, text: '#777777'}, dark: ok})).toHaveLength(2);
    });

    it('skips invalid or partial hex without throwing', () => {
        expect(contrastWarnings({light: {...ok, text: '#12'}, dark: {}})).toEqual([]);
        expect(contrastWarnings({})).toEqual([]);
    });

    it('describes a warning', () => {
        expect(describeWarning({scheme: 'light', pair: 'text/background', ratio: 3.2})).toBe(
            'Light: text on background 3.2:1, needs 4.5:1',
        );
    });
});

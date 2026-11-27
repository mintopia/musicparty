import {describe, expect, it} from 'vitest';
import {composeSearch, resolveSearch, setSingleToken} from '../utils/searchFilters';

const spec = {outcome: ['allow', 'reject'], provider: null};
const aliases = {rejected: 'reject'};

describe('searchFilters', () => {
    it('resolves typed tokens, aliases and keeps unknown values as text', () => {
        expect(resolveSearch('wham outcome:Rejected provider:Discord outcome:bogus', spec, aliases)).toEqual({
            text: 'wham outcome:bogus',
            filters: {outcome: 'reject', provider: 'Discord'},
        });
    });

    it('composes and replaces single-valued tokens', () => {
        expect(composeSearch('wham', {outcome: 'reject', provider: ''})).toBe('wham outcome:reject');
        expect(setSingleToken('wham outcome:allow', spec, 'outcome', 'reject')).toBe('wham outcome:reject');
        expect(setSingleToken('wham outcome:reject', spec, 'outcome', 'reject')).toBe('wham');
    });
});

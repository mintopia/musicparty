import {describe, expect, it} from 'vitest';
import {hasToken, parseSearch, serializeSearch, toggleToken} from '../utils/searchTokens';

describe('searchTokens', () => {
    it('parses free text, tokens, quoted values and repeated keys', () => {
        expect(parseSearch('abba artist:"foo bar" sent:no sent:yes by:x', ['artist', 'sent'])).toEqual({
            text: 'abba by:x',
            tokens: {artist: ['foo bar'], sent: ['no', 'yes']},
        });
        expect(parseSearch('Role:VIP http://x', ['role'])).toEqual({text: 'http://x', tokens: {role: ['VIP']}});
        expect(parseSearch('role:', ['role'])).toEqual({text: 'role:', tokens: {}});
        expect(parseSearch('', ['role'])).toEqual({text: '', tokens: {}});
    });

    it('serializes with quoting and round-trips', () => {
        const query = 'abba artist:"foo bar" sent:no';
        expect(serializeSearch(parseSearch(query, ['artist', 'sent']))).toBe(query);
    });

    it('toggles tokens on and off, keeping other content', () => {
        const on = toggleToken('abba', 'outcome', 'rejected');
        expect(on).toBe('abba outcome:rejected');
        expect(hasToken(on, 'outcome', 'rejected')).toBe(true);
        expect(toggleToken(`${on} outcome:flagged`, 'outcome', 'rejected')).toBe('abba outcome:flagged');
        expect(toggleToken(on, 'outcome', 'rejected')).toBe('abba');
        expect(hasToken('abba', 'outcome', 'rejected')).toBe(false);
        expect(toggleToken('', 'artist', 'foo bar')).toBe('artist:"foo bar"');
    });
});

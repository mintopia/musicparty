import {describe, expect, it} from 'vitest';
import labels from '../lib/partyLogLabels.json';
import {describePartyLogEntry, hasPartyLogLabel} from '../lib/partyLogLabels';

describe('party log labels', () => {
    it.each(Object.keys(labels))('renders a readable label for %s', (action) => {
        const text = describePartyLogEntry({action, subject: null, details: null});
        expect(text).not.toBe('');
        expect(text).not.toBe(action);
        expect(text).not.toMatch(/[{}[\]]/);
    });

    it('fills subject and old/new values', () => {
        expect(describePartyLogEntry({action: 'party.settings_changed', subject: 'name', details: {old: 'A', new: 'B'}})).toBe('Changed setting name: A -> B');
        expect(describePartyLogEntry({action: 'party.settings_changed', subject: 'name', details: null})).toBe('Changed setting name');
        expect(describePartyLogEntry({action: 'member.role_changed', subject: 'Sam', details: {old: 'guest', new: 'mod'}})).toBe('Changed Sam from guest to mod');
        expect(describePartyLogEntry({action: 'mod.settings_updated', subject: 'x', details: {changes: {a: 1, b: 2}}})).toBe('Changed Mod x settings: a, b');
    });

    it('falls back to the raw key for unknown actions', () => {
        expect(hasPartyLogLabel('nope.nothing')).toBe(false);
        expect(describePartyLogEntry({action: 'nope.nothing'})).toBe('nope.nothing');
    });
});

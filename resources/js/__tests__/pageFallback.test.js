import {describe, expect, it} from 'vitest';
import {pageFallback} from '../lib/pageFallback.js';

const party = {name: 'LAN Night', code: 'ABC'};

describe('pageFallback', () => {
    it.each([
        ['queue', 'Queue'],
        ['search', 'Search'],
        ['history', 'History'],
        ['party', 'Party'],
    ])('ends Party/Show breadcrumbs with the %s section name', (section, label) => {
        const {title, crumbs} = pageFallback('Party/Show', {party, section});
        expect(title).toBe(label);
        expect(crumbs.at(-1).label).toBe(label);
    });

    it('defaults Party/Show to Queue when the section is missing or unknown', () => {
        expect(pageFallback('Party/Show', {party}).title).toBe('Queue');
        expect(pageFallback('Party/Show', {party, section: 'bogus'}).title).toBe('Queue');
    });

    it.each([
        ['Party/PlayerTokens', 'Player Tokens'],
        ['Party/Blocklist', 'Blocklist'],
        ['Party/Members', 'Users'],
        ['Party/Tv', 'TV Mode'],
    ])('ends %s breadcrumbs with its visible page name', (component, label) => {
        const {title, crumbs} = pageFallback(component, {party});
        expect(title).toBe(label);
        expect(crumbs.at(-1).label).toBe(label);
    });

    it('never ends a Party page breadcrumb with its raw component name', () => {
        for (const component of ['Party/Show', 'Party/PlayerTokens', 'Party/BrowserPlayer']) {
            const {crumbs} = pageFallback(component, {party, section: 'search'});
            expect(crumbs.at(-1).label).not.toBe(component.split('/').pop());
        }
    });
});

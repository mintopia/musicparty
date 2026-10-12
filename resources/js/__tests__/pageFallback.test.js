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

    it.each(['Party/Song', 'Party/SomethingNew', 'Elsewhere/ShowThing', 'Mystery'])(
        'never shows the component name for %s',
        (component) => {
            const {title, crumbs} = pageFallback(component, {party});
            for (const label of [title, ...crumbs.map((c) => c.label)]) {
                expect(label).not.toMatch(/Song|Something|Show|Thing|Mystery/);
            }
        },
    );
});

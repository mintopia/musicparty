import {mount} from '@vue/test-utils';
import {describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get: vi.fn(), put: vi.fn(), delete: vi.fn()},
}));

import Decorations from '../Components/Decorations.vue';
import NowPlayingBanner from '../Components/NowPlayingBanner.vue';
import PlayedHistory from '../Components/PlayedHistory.vue';
import QueueList from '../Components/QueueList.vue';

const deco = (over = {}) => ({mod_id: 'x', badge: 'Hot', label: null, icon: null, accent: 'accent', variant: 'solid', ...over});
const track = {title: 'T', artists: ['A'], album: null, artwork_url: null, duration_ms: 1000, explicit: false};

const accents = ['accent', 'success', 'warning', 'danger', 'info', 'muted'];
const variants = ['solid', 'soft', 'outline'];
const combos = accents.flatMap((accent) => variants.map((variant) => [accent, variant]));

describe('Decorations', () => {
    it.each(combos)('renders %s %s with a distinct class set', (accent, variant) => {
        const w = mount(Decorations, {props: {decorations: [deco({accent, variant})]}});
        expect(w.get('[data-testid=decoration]').classes().join(' ')).toContain(variant === 'soft' ? '/15' : variant === 'outline' ? 'border' : 'bg-');
    });

    it('uses different classes for each combination', () => {
        const sets = new Set(combos.map(([accent, variant]) => mount(Decorations, {props: {decorations: [deco({accent, variant})]}}).get('[data-testid=decoration]').attributes('class')));
        expect(sets.size).toBe(combos.length);
    });

    it('renders badge, label and icon', () => {
        const w = mount(Decorations, {props: {decorations: [deco({label: 'Birthday', icon: 'gift'})]}});
        expect(w.get('[data-testid=decoration-badge]').text()).toBe('Hot');
        expect(w.get('[data-testid=decoration-label]').text()).toBe('Birthday');
        expect(w.find('svg').exists()).toBe(true);
    });

    it('escapes HTML in text', () => {
        const w = mount(Decorations, {props: {decorations: [deco({badge: '<b>bold</b>', label: '<img src=x onerror=alert(1)>'})]}});
        expect(w.find('b').exists()).toBe(false);
        expect(w.find('img').exists()).toBe(false);
        expect(w.get('[data-testid=decoration-badge]').text()).toBe('<b>bold</b>');
    });

    it('drops unknown accents and variants', () => {
        const w = mount(Decorations, {props: {decorations: [deco({accent: 'red'}), deco({variant: 'glow'}), deco({accent: 'bg-red-500 p-9'})]}});
        expect(w.find('[data-testid=decoration]').exists()).toBe(false);
        expect(w.find('[data-testid=decorations]').exists()).toBe(false);
    });

    it('drops unknown icons but keeps the text', () => {
        const w = mount(Decorations, {props: {decorations: [deco({icon: 'skull'})]}});
        expect(w.find('svg').exists()).toBe(false);
        expect(w.get('[data-testid=decoration-badge]').text()).toBe('Hot');
    });

    it('renders nothing for empty decorations or an empty entry', () => {
        expect(mount(Decorations, {props: {decorations: [deco({badge: null})]}}).find('[data-testid=decoration]').exists()).toBe(false);
        expect(mount(Decorations).find('[data-testid=decorations]').exists()).toBe(false);
        expect(mount(Decorations, {props: {decorations: null}}).find('[data-testid=decorations]').exists()).toBe(false);
    });
});

describe('decorations in core components', () => {
    const entry = (over = {}) => ({id: 1, track, status: 'queued', score: 0, my_vote: 0, requested_by: {name: 'Alex'}, ...over});

    it('QueueList shows decorations and tolerates their absence', () => {
        const w = mount(QueueList, {props: {partyCode: 'ABCD', queue: [entry({decorations: [deco({badge: '<i>x</i>'})]}), entry({id: 2})]}});
        const rows = w.findAll('[data-testid=queue-item]');
        expect(rows[0].get('[data-testid=decoration-badge]').text()).toBe('<i>x</i>');
        expect(rows[0].find('i').exists()).toBe(false);
        expect(rows[1].find('[data-testid=decoration]').exists()).toBe(false);
    });

    it('NowPlayingBanner shows decorations and tolerates their absence', () => {
        const withDeco = mount(NowPlayingBanner, {props: {partyCode: 'ABCD', nowPlaying: entry({decorations: [deco({label: 'Birthday', variant: 'outline'})]})}});
        expect(withDeco.get('[data-testid=decoration-label]').text()).toBe('Birthday');
        expect(mount(NowPlayingBanner, {props: {partyCode: 'ABCD', nowPlaying: entry()}}).find('[data-testid=decoration]').exists()).toBe(false);
    });

    it('PlayedHistory shows decorations and tolerates their absence', () => {
        const play = (over) => ({id: 1, track, requested_by: {name: 'A'}, votes: 1, score: 1, requested_at: '2026-10-09T11:00:00+00:00', played_at: '2026-10-09T12:00:00+00:00', my_rating: 0, ...over});
        const history = {data: [play({decorations: [deco({badge: 'Gold', accent: 'warning', icon: 'star'})]}), play({id: 2})], meta: {from: 1, to: 2, total: 2, links: []}};
        const w = mount(PlayedHistory, {props: {partyCode: 'ABCD', history}});
        const rows = w.findAll('[data-testid=history-item]');
        expect(rows[0].get('[data-testid=decoration-badge]').text()).toBe('Gold');
        expect(rows[1].find('[data-testid=decoration]').exists()).toBe(false);
    });
});

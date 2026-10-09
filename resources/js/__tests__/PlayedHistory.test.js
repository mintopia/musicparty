import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get: (...args) => get(...args)},
}));

import PlayedHistory from '../Components/PlayedHistory.vue';
import {formatPlayedAt} from '../lib/format';

const play = (over = {}) => ({
    id: 7,
    track: {title: 'Rewind the Night', artists: ['The Lowlands'], album: null, artwork_url: null, duration_ms: 200000, explicit: false},
    requested_by: {name: 'Alex'},
    votes: 4,
    score: 2,
    requested_at: '2026-10-09T11:00:00+00:00',
    likes: 3,
    dislikes: 1,
    my_rating: 0,
    played_at: '2026-10-09T12:00:00+00:00',
    ...over,
});

const mountHistory = (props = {}) => mount(PlayedHistory, {props: {partyCode: 'ABCD', history: {data: [play()], meta: {from: 1, to: 1, total: 1, links: []}}, ...props}});

beforeEach(() => {
    get.mockClear();
});

describe('PlayedHistory', () => {
    it('shows an empty state', () => {
        const w = mountHistory({history: {data: [], meta: {from: null, to: null, total: 0, links: []}}});

        expect(w.find('[data-testid=history-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=history-list]').exists()).toBe(false);
    });

    it('renders the play with its counts and requester', () => {
        const w = mountHistory();

        expect(w.text()).toContain('Rewind the Night');
        expect(w.get('[data-testid=history-votes]').text()).toBe('4');
        expect(w.get('[data-testid=history-score]').text()).toBe('2');
    });

    it('renders no rating controls in the table', () => {
        const w = mountHistory();

        expect(w.find('button[data-testid=rate-like]').exists()).toBe(false);
        expect(w.find('button[data-testid=rate-dislike]').exists()).toBe(false);
        expect(w.find('[data-testid=history-likes]').exists()).toBe(false);
        expect(w.find('[data-testid=history-dislikes]').exists()).toBe(false);
    });

    it('renders the mockup columns and the entries footer', () => {
        const w = mountHistory({history: {data: [play()], meta: {from: 26, to: 26, total: 26, links: []}}});

        expect(w.findAll('th').map((th) => th.text())).toEqual(['Song', 'Votes', 'Score', 'Requested', 'Sent to Spotify']);
        expect(w.get('[data-testid=history-entries]').text()).toBe('Showing 26 to 26 of 26 entries');
        expect(w.find('[data-testid=history-pager]').exists()).toBe(false);
    });

    it('stacks cells with labels for the mobile card layout', () => {
        const w = mountHistory();

        expect(w.findAll('[data-testid=history-item] td').map((td) => td.attributes('data-label'))).toEqual(['Song', 'Votes', 'Score', 'Requested', 'Sent to Spotify']);
    });

    it('builds pagination from the paginator links', () => {
        const links = [
            {url: null, label: '&laquo; Previous', active: false},
            {url: '/parties/ABCD/history?page=1', label: '1', active: true},
            {url: '/parties/ABCD/history?page=2', label: '2', active: false},
            {url: '/parties/ABCD/history?page=2', label: 'Next &raquo;', active: false},
        ];
        const w = mountHistory({history: {data: [play()], meta: {from: 1, to: 25, total: 30, links}}});

        expect(w.find('[data-testid=history-prev]').exists()).toBe(false);
        expect(w.find('[data-testid=history-prev-disabled]').exists()).toBe(true);
        expect(w.get('[data-testid=history-next]').attributes('href')).toBe('/parties/ABCD/history?page=2');
        expect(w.findAll('[data-testid=history-page]').map((a) => a.text())).toEqual(['1', '2']);
        expect(w.get('[aria-current=page]').text()).toBe('1');
    });

    it('submits only the filled filters to the history route', async () => {
        const w = mountHistory();
        await w.get('[data-testid=filter-name]').setValue('rewind');
        await w.get('[data-testid=filter-album]').setValue('  ');
        await w.get('[data-testid=filter-type]').setValue('requested');
        await w.get('[data-testid=history-filters]').trigger('submit');

        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'rewind', type: 'requested'}, expect.objectContaining({preserveState: true}));
    });

    it('omits the default type and starts from the active filters', async () => {
        const w = mountHistory({filters: {name: 'glass', artist: '', album: '', type: 'sent'}});

        expect(w.get('[data-testid=filter-name]').element.value).toBe('glass');
        await w.get('[data-testid=history-filters]').trigger('submit');
        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'glass'}, expect.any(Object));
    });

    it('explains an empty filtered result and keeps the filters visible', () => {
        const w = mountHistory({filters: {name: 'zzz', artist: '', album: '', type: 'sent'}, history: {data: [], meta: {from: null, to: null, total: 0, links: []}}});

        expect(w.get('[data-testid=history-empty]').text()).toBe('No songs match this search.');
        expect(w.get('[data-testid=history-entries]').text()).toBe('Showing 0 to 0 of 0 entries');
        expect(w.find('[data-testid=history-filters]').classes()).toContain('block');
    });

    it('collapses the filters on mobile until toggled', async () => {
        const w = mountHistory();

        expect(w.get('[data-testid=history-filters]').classes()).toContain('hidden');
        await w.get('[data-testid=history-filters-toggle]').trigger('click');
        expect(w.get('[data-testid=history-filters]').classes()).toContain('block');
        expect(w.get('[data-testid=history-filters-toggle]').attributes('aria-expanded')).toBe('true');
    });
});

describe('formatPlayedAt', () => {
    const now = Date.parse('2026-10-09T12:00:00+00:00');

    it.each([
        ['2026-10-09T11:59:59+00:00', '1 second ago'],
        ['2026-10-09T11:58:00+00:00', '2 minutes ago'],
        ['2026-10-09T11:00:00+00:00', '1 hour ago'],
        ['2026-10-07T12:00:00+00:00', '2 days ago'],
        ['not-a-date', ''],
    ])('formats %s', (iso, expected) => {
        expect(formatPlayedAt(iso, now)).toBe(expected);
    });
});

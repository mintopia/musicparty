import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();
const put = vi.fn();
const del = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {get: (...args) => get(...args), put: (...args) => put(...args), delete: (...args) => del(...args)},
}));

import PlayedHistory from '../Components/PlayedHistory.vue';
import {formatPlayedAt} from '../lib/format';

const play = (over = {}) => ({
    id: 7,
    kind: 'play',
    request_id: 31,
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
    put.mockReset();
    del.mockReset();
});

const choose = async (w, testId, label) => {
    const root = w.get(`[data-testid=${testId}]`);
    await root.get('button').trigger('click');
    const option = root.findAll('[role=option]').find((li) => li.text() === label);
    await option.trigger('mousedown');
};

describe('PlayedHistory', () => {
    it('shows an empty state', () => {
        const w = mountHistory({history: {data: [], meta: {from: null, to: null, total: 0, links: []}}});

        expect(w.find('[data-testid=history-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=history-list]').exists()).toBe(false);
    });

    it('highlights the viewer\'s own requests', () => {
        const w = mountHistory({history: {data: [play({id: 1, is_mine: true}), play({id: 2, is_mine: false})], meta: {from: 1, to: 2, total: 2, links: []}}});
        const rows = w.findAll('[data-testid=history-item]');

        expect(rows[0].attributes('data-mine')).toBe('true');
        expect(rows[1].attributes('data-mine')).toBeUndefined();
    });

    it('renders the play with its counts and requester', () => {
        const w = mountHistory();

        expect(w.text()).toContain('Rewind the Night');
        expect(w.get('[data-testid=history-votes]').text()).toBe('4');
        expect(w.get('[data-testid=history-score]').text()).toBe('2');
    });

    it('rates any play through the shared rating routes', async () => {
        const w = mountHistory();

        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', {value: 'up'}, expect.any(Object));
        await w.get('[data-testid=rate-dislike]').trigger('click');
        expect(put).toHaveBeenLastCalledWith('/parties/ABCD/plays/7/rating', {value: 'down'}, expect.any(Object));
    });

    it('retracts when the pressed rating is clicked again and highlights it', async () => {
        const w = mountHistory({history: {data: [play({my_rating: 1})], meta: {from: 1, to: 1, total: 1, links: []}}});

        expect(w.get('[data-testid=rate-like]').attributes('aria-pressed')).toBe('true');
        expect(w.get('[data-testid=rate-dislike]').attributes('aria-pressed')).toBe('false');
        await w.get('[data-testid=rate-like]').trigger('click');
        expect(del).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', expect.any(Object));
    });

    it('disables the rating buttons when read only', async () => {
        const w = mountHistory({readOnly: true});

        expect(w.get('[data-testid=rate-like]').attributes('disabled')).toBeDefined();
        await w.get('[data-testid=rate-dislike]').trigger('click');
        expect(put).not.toHaveBeenCalled();
    });

    it('renders the mockup columns and the entries footer', () => {
        const w = mountHistory({history: {data: [play()], meta: {from: 26, to: 26, total: 26, links: []}}});

        expect(w.findAll('th').map((th) => th.text())).toEqual(['SongArtist', 'Votes', 'Score', 'Requested', 'Sent to Spotify']);
        expect(w.get('[data-testid=history-entries]').text()).toBe('Showing 26 to 26 of 26 entries');
        expect(w.find('[data-testid=history-pager]').exists()).toBe(false);
    });

    it('keeps headers on one line and truncates song text so the table cannot overflow', () => {
        const wrapper = mountHistory();
        const headers = wrapper.findAll('thead th');
        expect(headers.every((th) => th.classes().includes('whitespace-nowrap'))).toBe(true);
        expect(wrapper.get('[data-testid="history-list"]').classes()).toContain('md:table-fixed');
        expect(wrapper.get('[data-testid="history-title"]').classes()).toContain('truncate');
        expect(wrapper.get('[data-testid="history-title"]').element.parentElement.classList.contains('min-w-0')).toBe(true);
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
        await w.get('[data-testid=history-filters]').trigger('submit');

        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'rewind'}, expect.objectContaining({preserveState: true}));
    });

    it('filters by type through the select field and navigates immediately', async () => {
        const w = mountHistory({filters: {name: 'glass'}});

        await choose(w, 'filter-type', 'Not sent');

        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'glass', type: 'unsent'}, expect.objectContaining({replace: true}));
        expect(w.find('select').exists()).toBe(false);
    });

    it('changes page size, dropping the page and keeping filters', async () => {
        const w = mountHistory({filters: {name: 'glass', type: 'played', sort: 'score', direction: 'desc'}});

        await choose(w, 'history-per-page', '50');

        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'glass', type: 'played', sort: 'score', direction: 'desc', per_page: 50}, expect.any(Object));
        await choose(w, 'history-per-page', '20');
        expect(get).toHaveBeenLastCalledWith('/parties/ABCD/history', {name: 'glass', type: 'played', sort: 'score', direction: 'desc'}, expect.any(Object));
    });

    it.each([
        ['name', {sort: 'name'}],
        ['artist', {sort: 'artist'}],
        ['requested', {sort: 'requested'}],
        ['score', {sort: 'score'}],
    ])('sorts by %s ascending from the default', async (key, expected) => {
        const w = mountHistory({filters: {artist: 'low'}});

        await w.get(`[data-testid=sort-${key}]`).trigger('click');

        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {artist: 'low', ...expected}, expect.any(Object));
    });

    it('sorts by played with the default sort cleared and toggles direction on the active column', async () => {
        const w = mountHistory({filters: {sort: 'played', direction: 'asc'}});

        expect(w.findAll('th')[4].attributes('aria-sort')).toBe('ascending');
        await w.get('[data-testid=sort-played]').trigger('click');
        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {direction: 'desc'}, expect.any(Object));

        await w.setProps({filters: {sort: 'played', direction: 'desc'}});
        expect(w.findAll('th')[4].attributes('aria-sort')).toBe('descending');
        await w.get('[data-testid=sort-played]').trigger('click');
        expect(get).toHaveBeenLastCalledWith('/parties/ABCD/history', {}, expect.any(Object));
    });

    it('marks only the active sort column', () => {
        const w = mountHistory({filters: {sort: 'score', direction: 'desc'}});

        expect(w.findAll('th').map((th) => th.attributes('aria-sort'))).toEqual(['none', undefined, 'descending', 'none', 'none']);
    });

    it('links the title to the song page when the request exists', () => {
        const w = mountHistory({history: {data: [play(), play({id: 8, request_id: null})], meta: {from: 1, to: 2, total: 2, links: []}}});
        const titles = w.findAll('[data-testid=history-title]');

        expect(titles[0].get('a').attributes('href')).toBe('/parties/ABCD/songs/31');
        expect(titles[1].find('a').exists()).toBe(false);
        expect(titles[1].text()).toBe('Rewind the Night');
    });

    it('renders unsent rows without a sent time or rating buttons', () => {
        const w = mountHistory({history: {data: [play({kind: 'unsent', played_at: null})], meta: {from: 1, to: 1, total: 1, links: []}}});

        expect(w.get('[data-testid=history-not-sent]').text()).toBe('Not sent');
        expect(w.find('[data-testid=history-played-at]').exists()).toBe(false);
        expect(w.find('[data-testid=history-rating]').exists()).toBe(false);
    });

    it('omits the default type and starts from the active filters', async () => {
        const w = mountHistory({filters: {name: 'glass', artist: '', album: '', type: 'all'}});

        expect(w.get('[data-testid=filter-name]').element.value).toBe('glass');
        await w.get('[data-testid=history-filters]').trigger('submit');
        expect(get).toHaveBeenCalledWith('/parties/ABCD/history', {name: 'glass'}, expect.any(Object));
    });

    it('explains an empty filtered result and keeps the filters visible', () => {
        const w = mountHistory({filters: {name: 'zzz', artist: '', album: '', type: 'all'}, history: {data: [], meta: {from: null, to: null, total: 0, links: []}}});

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

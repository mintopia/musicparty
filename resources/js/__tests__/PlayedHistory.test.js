import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';

const put = vi.fn();
const del = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {put: (...args) => put(...args), delete: (...args) => del(...args)},
}));

import PlayedHistory from '../Components/PlayedHistory.vue';
import {formatPlayedAt} from '../lib/format';

const play = (over = {}) => ({
    id: 7,
    track: {title: 'Rewind the Night', artists: ['The Lowlands'], album: null, artwork_url: null, duration_ms: 200000, explicit: false},
    requested_by: {name: 'Alex'},
    likes: 3,
    dislikes: 1,
    my_rating: 0,
    played_at: '2026-10-09T12:00:00+00:00',
    ...over,
});

const mountHistory = (props = {}) => mount(PlayedHistory, {props: {partyCode: 'ABCD', history: {data: [play()], links: {prev: null, next: null}}, ...props}});

beforeEach(() => {
    put.mockClear();
    del.mockClear();
});

describe('PlayedHistory', () => {
    it('shows an empty state', () => {
        const w = mountHistory({history: {data: [], links: {}}});

        expect(w.find('[data-testid=history-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=history-list]').exists()).toBe(false);
    });

    it('renders the play with its counts and requester', () => {
        const w = mountHistory();

        expect(w.text()).toContain('Rewind the Night');
        expect(w.text()).toContain('Requested by Alex');
        expect(w.get('[data-testid=history-likes]').text()).toBe('3');
        expect(w.get('[data-testid=history-dislikes]').text()).toBe('1');
    });

    it('likes, switches and retracts through the rating route', async () => {
        const w = mountHistory();
        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', {value: 'up'}, expect.any(Object));

        const liked = mountHistory({history: {data: [play({my_rating: 1})], links: {}}});
        await liked.get('[data-testid=rate-dislike]').trigger('click');
        expect(put).toHaveBeenLastCalledWith('/parties/ABCD/plays/7/rating', {value: 'down'}, expect.any(Object));

        await liked.get('[data-testid=rate-like]').trigger('click');
        expect(del).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', expect.any(Object));
    });

    it('disables rating when read-only', async () => {
        const w = mountHistory({readOnly: true});

        expect(w.get('[data-testid=rate-like]').attributes('disabled')).toBeDefined();
        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).not.toHaveBeenCalled();
    });

    it('links to the previous and next pages', () => {
        const w = mountHistory({history: {data: [play()], links: {prev: '/parties/ABCD/history?page=1', next: '/parties/ABCD/history?page=3'}}});

        expect(w.get('[data-testid=history-prev]').attributes('href')).toBe('/parties/ABCD/history?page=1');
        expect(w.get('[data-testid=history-next]').attributes('href')).toBe('/parties/ABCD/history?page=3');
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

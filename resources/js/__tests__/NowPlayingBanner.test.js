import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';

const put = vi.fn();
const del = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {put: (...args) => put(...args), delete: (...args) => del(...args)},
}));

import NowPlayingBanner from '../Components/NowPlayingBanner.vue';

const nowPlaying = {
    id: 1,
    track: {title: 'Dance Floor Gravity', artists: ['Nova Kids'], album: 'Orbit', artwork_url: null, duration_ms: 241000, explicit: false},
    status: 'playing',
    score: 0,
    requested_by: {name: 'Alex'},
};

const ratablePlay = (over = {}) => ({
    id: 7,
    track: {title: 'Last Train Home', artists: ['Harbor & Pine'], album: null, artwork_url: null, duration_ms: 1000, explicit: false},
    likes: 3,
    dislikes: 1,
    my_rating: 0,
    ...over,
});

const mountBanner = (props = {}) => mount(NowPlayingBanner, {props: {nowPlaying, partyCode: 'ABCD', ratablePlay: ratablePlay(), ...props}});

beforeEach(() => {
    put.mockReset();
    del.mockReset();
});

describe('NowPlayingBanner rating', () => {
    it('shows thumbs down, the like count and thumbs up', () => {
        const w = mountBanner();

        expect(w.get('[data-testid=rating-count]').text()).toBe('3');
        const order = w.get('[data-testid=now-playing-rating]').element.children;
        expect(order[0].dataset.testid).toBe('rate-dislike');
        expect(order[1].dataset.testid).toBe('rating-count');
        expect(order[2].dataset.testid).toBe('rate-like');
    });

    it('likes, switches and retracts through the rating route', async () => {
        const w = mountBanner();
        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', {value: 'up'}, expect.any(Object));

        const liked = mountBanner({ratablePlay: ratablePlay({my_rating: 1})});
        await liked.get('[data-testid=rate-dislike]').trigger('click');
        expect(put).toHaveBeenLastCalledWith('/parties/ABCD/plays/7/rating', {value: 'down'}, expect.any(Object));

        await liked.get('[data-testid=rate-like]').trigger('click');
        expect(del).toHaveBeenCalledWith('/parties/ABCD/plays/7/rating', expect.any(Object));
    });

    it('disables rating when read-only', async () => {
        const w = mountBanner({readOnly: true});

        expect(w.get('[data-testid=rate-like]').attributes('disabled')).toBeDefined();
        expect(w.get('[data-testid=rate-dislike]').attributes('disabled')).toBeDefined();
        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).not.toHaveBeenCalled();
    });

    it('hides the controls when nothing has been played', () => {
        expect(mountBanner({ratablePlay: null}).find('[data-testid=now-playing-rating]').exists()).toBe(false);
    });

    it('shows a rating error from the server', async () => {
        put.mockImplementation((url, data, options) => options.onError({rating: 'This party has ended, so ratings are closed.'}));
        const w = mountBanner();
        await w.get('[data-testid=rate-like]').trigger('click');

        expect(w.get('[data-testid=rating-error]').text()).toBe('This party has ended, so ratings are closed.');
    });
});

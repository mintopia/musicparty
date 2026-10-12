import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const put = vi.fn();
const del = vi.fn();
const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {post: (...args) => post(...args), put: (...args) => put(...args), delete: (...args) => del(...args)},
}));

import NowPlayingBanner from '../Components/NowPlayingBanner.vue';

const nowPlaying = {
    id: 1,
    track: {title: 'Dance Floor Gravity', artists: ['Nova Kids'], album: 'Orbit', artwork_url: 'https://img.test/a.jpg', duration_ms: 241000, explicit: false},
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
    post.mockReset();
});

describe('NowPlayingBanner rating', () => {
    it('shows thumbs down, the net rating and thumbs up', () => {
        const w = mountBanner();

        expect(w.get('[data-testid=rating-count]').text()).toBe('2');
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

describe('NowPlayingBanner layout', () => {
    it('renders the blurred backdrop, cover, title, artists and requester', () => {
        const w = mountBanner({positionMs: 0});

        expect(w.get('.backdrop-blur-\\[2px\\]').attributes('style')).toContain('https://img.test/a.jpg');
        expect(w.get('[data-testid=now-playing-cover]').attributes('src')).toBe('https://img.test/a.jpg');
        expect(w.get('[data-testid=now-playing-title]').text()).toBe('Dance Floor Gravity');
        expect(w.get('[data-testid=now-playing-artist]').text()).toBe('Nova Kids');
        expect(w.get('[data-testid=now-playing-requester]').text()).toContain('Alex');
        expect(w.get('[data-testid=now-playing-progress]').classes()).toContain('h-[5px]');
        expect(w.classes()).not.toContain('hidden');
    });

    it('says there is nothing playing when idle', () => {
        const w = mountBanner({nowPlaying: null, positionMs: null});

        expect(w.get('[data-testid=now-playing-empty]').text()).toContain('There is nothing playing');
        expect(w.find('[data-testid=now-playing-progress]').exists()).toBe(false);
    });
});

describe('NowPlayingBanner progress', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('advances elapsed each second, shows total and freezes while paused', async () => {
        const w = mountBanner({positionMs: 60000});
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('1:00');
        expect(w.get('[data-testid=now-playing-total]').text()).toBe('4:01');

        vi.advanceTimersByTime(2000);
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('1:02');

        await w.setProps({paused: true});
        vi.advanceTimersByTime(5000);
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('1:02');

        await w.setProps({paused: false});
        vi.advanceTimersByTime(3000);
        await w.vm.$nextTick();
        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('1:05');
    });

    it('does not run past the track length', async () => {
        const w = mountBanner({positionMs: 240000});
        vi.advanceTimersByTime(10000);
        await w.vm.$nextTick();

        expect(w.get('[data-testid=now-playing-elapsed]').text()).toBe('4:01');
    });
});

describe('NowPlayingBanner playback controls', () => {
    const supported = {play: true, pause: true, skip: true};

    it('is absent unless the viewer may control playback', () => {
        expect(mountBanner({capabilities: supported}).find('[data-testid=playback-controls]').exists()).toBe(false);
    });

    it('posts pause, play and skip for a host', async () => {
        const w = mountBanner({canControl: true, capabilities: supported, positionMs: 0});
        expect(w.get('[data-testid=playback-toggle]').attributes('aria-label')).toBe('Pause');
        await w.get('[data-testid=playback-toggle]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/ABCD/playback/pause', {}, expect.any(Object));

        await w.setProps({paused: true});
        expect(w.get('[data-testid=playback-toggle]').attributes('aria-label')).toBe('Play');
        await w.get('[data-testid=playback-toggle]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/ABCD/playback/play', {}, expect.any(Object));

        await w.get('[data-testid=playback-skip]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/ABCD/playback/skip', {}, expect.any(Object));
    });

    it('disables the controls when the Player does not support them', async () => {
        const w = mountBanner({canControl: true, capabilities: {play: false, pause: false, skip: false}});

        expect(w.get('[data-testid=playback-toggle]').attributes('disabled')).toBeDefined();
        expect(w.get('[data-testid=playback-skip]').attributes('disabled')).toBeDefined();
        await w.get('[data-testid=playback-skip]').trigger('click');
        expect(post).not.toHaveBeenCalled();
    });

    it('shows a refusal returned by the server', async () => {
        post.mockImplementationOnce((url, data, options) => options.onError({playback: 'The Player is disconnected.'}));
        const w = mountBanner({canControl: true, capabilities: supported});
        await w.get('[data-testid=playback-skip]').trigger('click');

        expect(w.get('[data-testid=playback-error]').text()).toContain('disconnected');
    });
});

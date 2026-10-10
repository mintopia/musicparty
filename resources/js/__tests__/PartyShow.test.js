import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const reload = vi.fn();
const put = vi.fn();
const destroy = vi.fn();
const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {reload: (...args) => reload(...args), post: (...args) => post(...args), put: (...args) => put(...args), delete: (...args) => destroy(...args)},
    usePage: () => ({props: {errors: {}}}),
}));

import Show from '../pages/Party/Show.vue';
import MiniNowPlaying from '../Components/MiniNowPlaying.vue';

const entry = (over = {}) => ({
    id: 1,
    track: {title: 'Dance Floor Gravity', artists: ['Nova Kids'], album: 'Orbit', artwork_url: null, duration_ms: 241000, explicit: false},
    status: 'playing',
    score: 0,
    requested_by: {name: 'Alex'},
    ...over,
});

const baseProps = (over = {}) => ({
    party: {code: 'FRI123', name: 'Friday Night LAN', state: 'live', downvotes: true},
    membership: {role: 'member', banned: false},
    section: 'queue',
    nowPlaying: entry(),
    upNext: entry({id: 2, status: 'up_next', score: 3, track: {title: 'Lights Out', artists: ['LAN Party Allstars'], album: null, artwork_url: null, duration_ms: 193000, explicit: false}, requested_by: {name: 'Riley'}}),
    queue: [],
    ...over,
});

let listeners;
let echo;

beforeEach(() => {
    listeners = {};
    echo = {
        channel: vi.fn(() => ({
            listen: vi.fn((event, cb) => {
                listeners[event] = cb;
            }),
        })),
        leave: vi.fn(),
    };
    window.Echo = echo;
    reload.mockClear();
    put.mockClear();
    destroy.mockClear();
    post.mockClear();
});

afterEach(() => {
    delete window.Echo;
});

describe('Party page now playing and Up Next', () => {
    it('shows the now playing track', () => {
        const w = mount(Show, {props: baseProps()});
        expect(w.get('[data-testid=now-playing-title]').text()).toBe('Dance Floor Gravity');
        expect(w.get('[data-testid=now-playing-artist]').text()).toBe('Nova Kids');
        expect(w.get('[data-testid=now-playing-requester]').text()).toBe('Requested by Alex');
    });

    it('shows Up Next as a locked card with its votes', () => {
        const w = mount(Show, {props: baseProps()});
        const card = w.get('[data-testid=up-next-card]');
        expect(card.text()).toContain('Lights Out');
        expect(card.text()).toContain('Requested by Riley');
        expect(w.get('[data-testid=up-next-votes]').text()).toBe('3 votes');
        expect(w.find('[data-testid=up-next-locked]').exists()).toBe(true);
        expect(card.find('button').exists()).toBe(false);
    });

    it('handles a null requester for fallback requests', () => {
        const w = mount(Show, {props: baseProps({
            nowPlaying: entry({requested_by: {name: null}}),
            upNext: entry({id: 2, status: 'up_next', requested_by: {name: null}}),
        })});
        expect(w.get('[data-testid=now-playing-requester]').text()).toBe('Fallback playlist');
        expect(w.get('[data-testid=up-next-card]').text()).toContain('Fallback playlist');
        expect(w.text()).not.toContain('Requested by null');
    });

    it('shows empty states when nothing is playing or up next', () => {
        const w = mount(Show, {props: baseProps({nowPlaying: null, upNext: null})});
        expect(w.find('[data-testid=now-playing-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=up-next-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=up-next-card]').exists()).toBe(false);
    });

    describe('QueueUpdatedEvent reloads', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('coalesces 10 events in 2 seconds into one async reload inside the jitter window', () => {
            const w = mount(Show, {props: baseProps()});
            expect(echo.channel).toHaveBeenCalledWith('party.FRI123');
            for (let i = 0; i < 10; i++) {
                listeners['Party.QueueUpdatedEvent']();
                vi.advanceTimersByTime(200);
            }
            expect(reload).not.toHaveBeenCalled();
            vi.advanceTimersByTime(499);
            expect(reload).not.toHaveBeenCalled();
            vi.advanceTimersByTime(1501);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledWith({only: ['queue', 'nowPlaying', 'upNext', 'ratablePlay'], preserveScroll: true, async: true});
            vi.advanceTimersByTime(10000);
            expect(reload).toHaveBeenCalledTimes(1);
            w.unmount();
            expect(echo.leave).toHaveBeenCalledWith('party.FRI123');
        });

        it('waits at least the trailing debounce and adds jitter', () => {
            const random = vi.spyOn(Math, 'random').mockReturnValue(0);
            const w = mount(Show, {props: baseProps()});
            listeners['Party.QueueUpdatedEvent']();
            vi.advanceTimersByTime(499);
            expect(reload).not.toHaveBeenCalled();
            vi.advanceTimersByTime(1);
            expect(reload).toHaveBeenCalledTimes(1);
            random.mockReturnValue(0.999);
            listeners['Party.QueueUpdatedEvent']();
            vi.advanceTimersByTime(1990);
            expect(reload).toHaveBeenCalledTimes(1);
            vi.advanceTimersByTime(10);
            expect(reload).toHaveBeenCalledTimes(2);
            random.mockRestore();
            w.unmount();
        });

        it('does not cancel an in-flight vote because the reload is async', () => {
            const w = mount(Show, {props: baseProps({queue: [entry({id: 5, status: 'queued', my_vote: 0})]})});
            w.get('[data-testid=vote-up]').trigger('click');
            expect(put).toHaveBeenCalledTimes(1);
            listeners['Party.QueueUpdatedEvent']();
            vi.advanceTimersByTime(2000);
            expect(reload.mock.calls[0][0].async).toBe(true);
            w.unmount();
        });

        it('drops a pending reload on unmount', () => {
            const w = mount(Show, {props: baseProps()});
            listeners['Party.QueueUpdatedEvent']();
            w.unmount();
            vi.advanceTimersByTime(5000);
            expect(reload).not.toHaveBeenCalled();
        });
    });
});

describe('Party page songs section', () => {
    const play = {id: 7, track: {title: 'T', artists: ['A'], album: null, artwork_url: null, duration_ms: 1, explicit: false}, likes: 2, dislikes: 0, my_rating: 0};

    it('titles the history section Songs and keeps the banner with rating', () => {
        const w = mount(Show, {props: baseProps({section: 'history', history: {data: [], meta: {}}, ratablePlay: play})});

        expect(w.findAll('h2').map((h) => h.text())).toContain('Songs');
        expect(w.find('[data-testid=now-playing]').exists()).toBe(true);
        expect(w.get('[data-testid=rate-like]').attributes('disabled')).toBeUndefined();
    });

    it('locks rating once the party has ended', () => {
        const w = mount(Show, {props: baseProps({party: {code: 'FRI123', name: 'F', state: 'ended', downvotes: true}, readOnly: true, ratablePlay: play})});

        expect(w.get('[data-testid=rate-like]').attributes('disabled')).toBeDefined();
    });
});

describe('MiniNowPlaying', () => {
    it('uses the real now playing data', () => {
        const w = mount(MiniNowPlaying, {props: {nowPlaying: entry()}});
        expect(w.text()).toContain('Dance Floor Gravity');
        expect(w.text()).toContain('Nova Kids');
    });

    it('shows an empty state', () => {
        expect(mount(MiniNowPlaying, {props: {nowPlaying: null}}).text()).toContain('Nothing playing');
    });
});

describe('rating', () => {
    const play = (over = {}) => ({id: 7, track: {title: 'T', artists: ['A'], album: null, artwork_url: null, duration_ms: 1, explicit: false}, likes: 3, dislikes: 1, my_rating: 0, ...over});

    it('likes, switches and retracts from MiniNowPlaying against the Play rating route', async () => {
        const w = mount(MiniNowPlaying, {props: {nowPlaying: entry(), partyCode: 'FRI123', ratablePlay: play()}});
        expect(w.find('[data-testid="rating-count"]').text()).toBe('2');

        await w.find('[data-testid="rating-like"]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/FRI123/plays/7/rating', {value: 'up'}, expect.any(Object));

        await w.setProps({ratablePlay: play({my_rating: 1})});
        await w.find('[data-testid="rating-like"]').trigger('click');
        expect(destroy).toHaveBeenCalledWith('/parties/FRI123/plays/7/rating', expect.any(Object));

        await w.find('[data-testid="rating-dislike"]').trigger('click');
        expect(put).toHaveBeenLastCalledWith('/parties/FRI123/plays/7/rating', {value: 'down'}, expect.any(Object));
    });

    it('rates the Play from the Show banner', async () => {
        const w = mount(Show, {props: baseProps({ratablePlay: play()})});

        await w.get('[data-testid=rate-like]').trigger('click');
        expect(put).toHaveBeenCalledWith('/parties/FRI123/plays/7/rating', {value: 'up'}, expect.any(Object));
    });

    it('hides the mini rating without a ratable play', () => {
        expect(mount(MiniNowPlaying, {props: {nowPlaying: entry(), ratablePlay: null}}).find('[data-testid="rating"]').exists()).toBe(false);
    });

    it('hides the buttons when read only or nothing is playing', () => {
        expect(mount(MiniNowPlaying, {props: {nowPlaying: entry(), ratablePlay: play(), readOnly: true}}).find('[data-testid="rating"]').exists()).toBe(false);
        expect(mount(MiniNowPlaying, {props: {nowPlaying: null}}).find('[data-testid="rating"]').exists()).toBe(false);
        expect(mount(Show, {props: baseProps({party: {code: 'FRI123', name: 'Friday Night LAN', state: 'ended', downvotes: true}})}).find('[data-testid="rating"]').exists()).toBe(false);
    });
});

describe('playback controls', () => {
    it('shows the controls only to a manager and posts each control', async () => {
        expect(mount(Show, {props: baseProps()}).find('[data-testid="playback-controls"]').exists()).toBe(false);

        const w = mount(Show, {props: baseProps({canManage: true})});
        await w.find('[data-testid="playback-play"]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/FRI123/playback/play', {}, expect.any(Object));
        await w.find('[data-testid="playback-pause"]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/FRI123/playback/pause', {}, expect.any(Object));
        await w.find('[data-testid="playback-skip"]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/FRI123/playback/skip', {}, expect.any(Object));

        await w.find('[data-testid="playback-seek-input"]').setValue('12');
        await w.find('[data-testid="playback-seek"]').trigger('click');
        expect(post).toHaveBeenLastCalledWith('/parties/FRI123/playback/seek', {position_ms: 12000}, expect.any(Object));

        await w.find('[data-testid="playback-volume-input"]').setValue('80');
        await w.find('[data-testid="playback-volume-input"]').trigger('change');
        expect(post).toHaveBeenLastCalledWith('/parties/FRI123/playback/volume', {level: 80}, expect.any(Object));
    });

    it('shows a refusal returned by the server', async () => {
        post.mockImplementationOnce((_url, _data, options) => options.onError({playback: 'The Player is disconnected, so playback cannot be controlled.'}));
        const w = mount(Show, {props: baseProps({canManage: true})});
        await w.find('[data-testid="playback-play"]').trigger('click');
        expect(w.find('[data-testid="playback-error"]').text()).toContain('disconnected');
    });
});

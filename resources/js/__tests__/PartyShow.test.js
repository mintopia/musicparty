import {enableAutoUnmount, mount} from '@vue/test-utils';
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

enableAutoUnmount(afterEach);

let listeners;
let echo;

const subscription = () => {
    const channel = {
        listen: vi.fn((event, cb) => {
            listeners[event] = cb;

            return channel;
        }),
    };

    return channel;
};

beforeEach(() => {
    listeners = {};
    echo = {
        channel: vi.fn(() => subscription()),
        private: vi.fn(() => subscription()),
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

    describe('realtime state', () => {
        const payload = (over = {}) => ({
            version: 1,
            code: 'FRI123',
            now_playing: null,
            up_next: null,
            queue: [],
            ...over,
        });

        it('applies a Queue event to the list without a router call', async () => {
            const w = mount(Show, {props: baseProps({queue: [entry({id: 5, status: 'queued', track: {...entry().track, title: 'Old'}})]})});
            listeners['.queue.updated'](payload({queue: [entry({id: 6, status: 'queued', track: {...entry().track, title: 'Fresh'}})]}));
            await w.vm.$nextTick();
            const items = w.findAll('[data-testid=queue-item]');
            expect(items).toHaveLength(1);
            expect(items[0].text()).toContain('Fresh');
            expect(reload).not.toHaveBeenCalled();
        });

        it('keeps the Member upvote highlight across a Queue event', async () => {
            const w = mount(Show, {props: baseProps({queue: [entry({id: 5, status: 'queued', my_vote: 1})]})});
            expect(w.get('[data-testid=vote-up]').attributes('aria-pressed')).toBe('true');
            listeners['.queue.updated'](payload({queue: [entry({id: 5, status: 'queued', score: 4})]}));
            await w.vm.$nextTick();
            expect(w.get('[data-testid=vote-up]').attributes('aria-pressed')).toBe('true');
        });

        it('updates the highlight from a member vote event', async () => {
            const w = mount(Show, {props: baseProps({membership: {id: 9, role: 'member', banned: false}, queue: [entry({id: 5, status: 'queued', my_vote: 0})]})});
            expect(echo.private).toHaveBeenCalledWith('party.FRI123.member.9');
            listeners['.member.vote_changed']({request_id: 5, value: 1});
            await w.vm.$nextTick();
            expect(w.get('[data-testid=vote-up]').attributes('aria-pressed')).toBe('true');
            listeners['.member.vote_changed']({request_id: 5, value: 0});
            await w.vm.$nextTick();
            expect(w.get('[data-testid=vote-up]').attributes('aria-pressed')).toBe('false');
        });

        it('shows the Member rating from page props and member events', async () => {
            const ratable = {id: 7, track: entry().track, likes: 2, dislikes: 0, my_rating: 0};
            const w = mount(Show, {props: baseProps({ratablePlay: ratable, memberVotes: {votes: [], ratings: [{play_id: 7, value: 1}]}})});
            expect(w.get('[data-testid=rate-like]').attributes('aria-pressed')).toBe('true');
            listeners['.member.rating_changed']({play_id: 7, value: -1});
            await w.vm.$nextTick();
            expect(w.get('[data-testid=rate-dislike]').attributes('aria-pressed')).toBe('true');
        });

        it('follows the now playing Track for rating', async () => {
            const w = mount(Show, {props: baseProps({ratablePlay: {id: 7, track: entry().track, likes: 0, dislikes: 0, my_rating: 0}})});
            listeners['.queue.updated'](payload({now_playing: entry({id: 8, play_id: 12, likes: 3, dislikes: 1})}));
            listeners['.member.rating_changed']({play_id: 12, value: 1});
            await w.vm.$nextTick();
            expect(w.get('[data-testid=rating-count]').text()).toBe('3');
            expect(w.get('[data-testid=rate-like]').attributes('aria-pressed')).toBe('true');
        });

        it('reloads once on realtime:resync and stops after unmount', () => {
            const w = mount(Show, {props: baseProps()});
            window.dispatchEvent(new Event('realtime:resync'));
            expect(reload).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledWith({only: ['queue', 'nowPlaying', 'upNext', 'ratablePlay', 'memberVotes'], preserveScroll: true, async: true});
            w.unmount();
            window.dispatchEvent(new Event('realtime:resync'));
            expect(reload).toHaveBeenCalledTimes(1);
            expect(echo.leave).toHaveBeenCalledWith('party.FRI123');
        });

        it('reloads when the payload version is newer than the client knows', () => {
            mount(Show, {props: baseProps()});
            listeners['.queue.updated'](payload({version: 2}));
            expect(reload).toHaveBeenCalledTimes(1);
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

describe('Party Show realtime state and toasts', () => {
    it('applies party.state_changed without reload', async () => {
        const w = mount(Show, {props: baseProps()});
        listeners['.party.state_changed']({state: 'ended'});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=party-state]').text()).toBe('ended');
        expect(reload).not.toHaveBeenCalled();
    });

    it('toasts for rejected, approved and banned, but not for other decisions', async () => {
        const w = mount(Show, {props: baseProps()});
        listeners['.request.rejected']({provider_track_id: 'x', reason: 'Too loud'});
        listeners['.request.decided']({request_id: 1, status: 'rejected', reason: null});
        await w.vm.$nextTick();
        expect(w.findAll('[data-testid=toast]')).toHaveLength(1);
        expect(w.get('[data-testid=toast]').text()).toContain('Too loud');

        listeners['.request.decided']({request_id: 1, status: 'queued', reason: null});
        listeners['.member.banned']({member_id: 5});
        await w.vm.$nextTick();
        const texts = w.findAll('[data-testid=toast]').map((t) => t.text());
        expect(texts).toHaveLength(3);
        expect(texts[1]).toContain('approved');
        expect(texts[2]).toContain('banned');
    });
});

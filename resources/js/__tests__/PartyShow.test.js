import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: {reload: (...args) => reload(...args), post: vi.fn(), put: vi.fn(), delete: vi.fn()},
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

    it('reloads now playing and up next live on QueueUpdatedEvent', () => {
        const w = mount(Show, {props: baseProps()});
        expect(echo.channel).toHaveBeenCalledWith('party.FRI123');
        listeners['Party.QueueUpdatedEvent']();
        expect(reload).toHaveBeenCalledWith({only: ['queue', 'nowPlaying', 'upNext'], preserveScroll: true});
        w.unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.FRI123');
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

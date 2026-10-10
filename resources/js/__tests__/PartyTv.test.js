import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
}));

import Tv from '../pages/Party/Tv.vue';

const entry = (over = {}) => ({
    id: 1,
    track: {title: 'Dance Floor Gravity', artists: ['Nova Kids'], album: 'Orbit', artwork_url: null, duration_ms: 241000, explicit: false},
    status: 'playing',
    score: 0,
    requested_by: {name: 'Alex'},
    ...over,
});

const props = (over = {}) => ({
    party: {code: 'FRI1', name: 'Friday Night LAN', state: 'live', joinUrl: 'http://localhost/parties/FRI1'},
    nowPlaying: entry(),
    upNext: entry({id: 2, track: {title: 'Lights Out', artists: ['Allstars'], album: null, artwork_url: null, duration_ms: 193000, explicit: false}}),
    sequence: 5,
    startedAt: null,
    ...over,
});

let listeners;
let echo;

beforeEach(() => {
    listeners = {};
    echo = {
        channel: vi.fn(() => ({listen: vi.fn((event, cb) => { listeners[event] = cb; })})),
        private: vi.fn(),
        join: vi.fn(),
        leave: vi.fn(),
    };
    window.Echo = echo;
});

afterEach(() => {
    delete window.Echo;
});

describe('TV screen', () => {
    it('shows party name, code, now playing, up next and a QR code', () => {
        const w = mount(Tv, {props: props()});
        expect(w.get('[data-testid=tv-party-name]').text()).toBe('Friday Night LAN');
        expect(w.get('[data-testid=tv-party-code]').text()).toBe('FRI1');
        expect(w.get('[data-testid=tv-now-playing-title]').text()).toBe('Dance Floor Gravity');
        expect(w.get('[data-testid=tv-now-playing-requester]').text()).toBe('Requested by Alex');
        expect(w.get('[data-testid=tv-up-next-title]').text()).toBe('Lights Out');
        expect(w.get('[data-testid=qr-code] svg').exists()).toBe(true);
    });

    it('shows score, total time, progress and a backdrop', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T00:02:42Z'));
        const w = mount(Tv, {props: props({
            startedAt: '2026-01-01T00:00:00Z',
            nowPlaying: entry({score: 3, track: {title: 'T', artists: ['A'], album: null, artwork_url: 'http://x/a.jpg', duration_ms: 241000, explicit: false}}),
        })});
        expect(w.get('[data-testid=tv-now-playing-score]').text()).toBe('3');
        expect(w.get('[data-testid=tv-elapsed]').text()).toBe('2:42');
        expect(w.get('[data-testid=tv-now-playing-duration]').text()).toBe('4:01');
        expect(w.get('[data-testid=tv-progress-fill]').attributes('style')).toContain('width: 67');
        expect(w.get('[data-testid=tv-backdrop]').attributes('style')).toContain('http://x/a.jpg');
        w.unmount();
        vi.useRealTimers();
    });

    it('shows no progress without a start time', () => {
        const w = mount(Tv, {props: props()});
        expect(w.get('[data-testid=tv-elapsed]').text()).toBe('0:00');
        expect(w.get('[data-testid=tv-progress-fill]').attributes('style')).toContain('width: 0%');
    });

    it('shows empty states', () => {
        const w = mount(Tv, {props: props({nowPlaying: null, upNext: null})});
        expect(w.find('[data-testid=tv-now-playing-empty]').exists()).toBe(true);
        expect(w.find('[data-testid=tv-up-next-empty]').exists()).toBe(true);
    });

    it('subscribes only to the public party channel', () => {
        mount(Tv, {props: props()});
        expect(echo.channel).toHaveBeenCalledWith('party.FRI1');
        expect(echo.private).not.toHaveBeenCalled();
        expect(echo.join).not.toHaveBeenCalled();
    });

    it('applies newer snapshots and ignores stale ones', async () => {
        const w = mount(Tv, {props: props()});
        const next = entry({id: 9, track: {title: 'Fresh', artists: ['X'], album: null, artwork_url: null, duration_ms: 1000, explicit: false}});

        listeners['Party.QueueUpdatedEvent']({sequence: 6, now_playing: next, up_next: null});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=tv-now-playing-title]').text()).toBe('Fresh');
        expect(w.find('[data-testid=tv-up-next-empty]').exists()).toBe(true);

        listeners['Party.QueueUpdatedEvent']({sequence: 5, now_playing: entry(), up_next: null});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=tv-now-playing-title]').text()).toBe('Fresh');
    });

    it('leaves the channel on unmount', () => {
        const w = mount(Tv, {props: props()});
        w.unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.FRI1');
    });
});

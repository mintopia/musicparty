import {enableAutoUnmount, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    router: {reload: (...args) => reload(...args)},
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
    startedAt: null,
    ...over,
});

enableAutoUnmount(afterEach);

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
    reload.mockReset();
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

    it('applies every snapshot it receives, including after a reload with any payload', async () => {
        const w = mount(Tv, {props: props()});
        const next = entry({id: 9, track: {title: 'Fresh', artists: ['X'], album: null, artwork_url: null, duration_ms: 1000, explicit: false}});

        listeners['Party.QueueUpdatedEvent']({now_playing: next, up_next: null});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=tv-now-playing-title]').text()).toBe('Fresh');
        expect(w.find('[data-testid=tv-up-next-empty]').exists()).toBe(true);

        const earlier = entry({id: 3, track: {title: 'Earlier', artists: ['Y'], album: null, artwork_url: null, duration_ms: 1000, explicit: false}});
        listeners['Party.QueueUpdatedEvent']({now_playing: earlier, up_next: null});
        await w.vm.$nextTick();
        expect(w.get('[data-testid=tv-now-playing-title]').text()).toBe('Earlier');
    });

    it('leaves the channel on unmount', () => {
        const w = mount(Tv, {props: props()});
        w.unmount();
        expect(echo.leave).toHaveBeenCalledWith('party.FRI1');
    });

    describe('party theme', () => {
        const theme = (over = {}) => ({
            light: {primary: '#abcdef'},
            dark: {primary: '#123456'},
            font: 'inter',
            logo_url: '/logo.png',
            logo_dark_url: '/logo-dark.png',
            background_url: '/bg.png',
            tv_layout: 'compact',
            ...over,
        });

        it('shows logo, background and layout class from the theme', () => {
            const w = mount(Tv, {props: props({theme: theme()})});
            expect(w.get('[data-testid=tv-logo]').attributes('src')).toBe('/logo.png');
            expect(w.get('[data-testid=tv-theme-background]').attributes('style')).toContain('/bg.png');
            expect(w.get('[data-testid=tv-screen]').classes()).toContain('tv-layout-compact');
        });

        it('updates live on ThemeUpdated and cleans up on unmount', async () => {
            const w = mount(Tv, {props: props({theme: theme()})});
            listeners['.ThemeUpdated'](theme({light: {primary: '#ff0000'}, logo_url: null, background_url: null, tv_layout: 'queue-focus'}));
            await w.vm.$nextTick();
            expect(document.getElementById('party-theme-live').textContent).toContain('--color-primary:#ff0000;');
            expect(w.find('[data-testid=tv-logo]').exists()).toBe(false);
            expect(w.find('[data-testid=tv-theme-background]').exists()).toBe(false);
            expect(w.get('[data-testid=tv-screen]').classes()).toContain('tv-layout-queue-focus');
            w.unmount();
            expect(document.getElementById('party-theme-live')).toBeNull();
        });
    });

    it('reloads on realtime:resync and applies the fresh props', () => {
        const w = mount(Tv, {props: props()});
        window.dispatchEvent(new Event('realtime:resync'));
        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload.mock.calls[0][0].only).toEqual(['nowPlaying', 'upNext', 'startedAt']);
        w.unmount();
        window.dispatchEvent(new Event('realtime:resync'));
        expect(reload).toHaveBeenCalledTimes(1);
    });
});

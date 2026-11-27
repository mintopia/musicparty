import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {nextTick, ref} from 'vue';
import Tv from '@/pages/Parties/Tv.vue';

const mocks = vi.hoisted(() => ({
    reload: vi.fn(),
    channels: [] as string[],
    handler: null as null | ((payload: unknown) => void),
    reconnect: null as null | (() => void),
    connected: null as null | {value: boolean},
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    usePage: () => ({props: {}}),
    router: {reload: mocks.reload},
}));

vi.mock('@/layouts/BareLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        publicChannel: (name: string) => {
            mocks.channels.push(name);
            const channel = {
                listen: (_event: string, cb: (payload: unknown) => void) => {
                    mocks.handler = cb;
                    return channel;
                },
            };
            return channel;
        },
        onReconnect: (cb: () => void) => {
            mocks.reconnect = cb;
        },
        trackConnection: () => mocks.connected,
    }),
}));

const track = (extra: Record<string, unknown> = {}) => ({
    provider_track_id: 't1',
    name: 'Song',
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 200000,
    score: 1,
    user: null,
    css_classes: '',
    fallback_name: 'From Fallback Playlist',
    ...extra,
});

const playing = (isPlaying: boolean) => ({
    isPlaying,
    sampledAt: '2030-01-01T00:00:30Z',
    progressMs: 10000,
    length: 200000,
});

const state = (extra: Record<string, unknown> = {}) => ({
    name: 'P',
    now: track(),
    next: null,
    current: {id: 1, provider_track_id: 't1', user: null, rating: 3},
    status: playing(true),
    show_qrcode: false,
    ...extra,
});

const party = {
    code: 'ABC',
    name: 'P',
    url: 'https://x/parties/ABC',
    qrSvg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
};

describe('Parties/Tv', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-01T00:01:00Z'));
        mocks.reload.mockClear();
        mocks.channels.length = 0;
        mocks.connected = ref(true);
    });
    afterEach(() => {
        vi.useRealTimers();
        Reflect.deleteProperty(navigator, 'wakeLock');
    });

    it('subscribes to party.{code} and renders pushed state', async () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        expect(mocks.channels).toEqual(['party.ABC']);
        expect(wrapper.text()).toContain('0:40');

        mocks.handler?.(state({now: track({name: 'Pushed Song'})}));
        await nextTick();

        expect(wrapper.text()).toContain('Pushed Song');
        wrapper.unmount();
    });

    it('restarts the progress timer after pause then resume', async () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        expect(vi.getTimerCount()).toBe(1);

        mocks.handler?.(state({status: playing(false)}));
        await nextTick();
        expect(vi.getTimerCount()).toBe(0);

        mocks.handler?.(state({status: {...playing(true), sampledAt: '2030-01-01T00:01:00Z', progressMs: 0}}));
        await nextTick();
        expect(vi.getTimerCount()).toBe(1);

        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(wrapper.text()).toContain('0:05');
        wrapper.unmount();
    });

    it('clears the interval on unmount', () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        expect(vi.getTimerCount()).toBe(1);
        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('leaves no timers behind after many mount/unmount cycles', () => {
        for (let i = 0; i < 200; i++) {
            mount(Tv, {props: {party, state: state()}}).unmount();
        }
        expect(vi.getTimerCount()).toBe(0);
    });

    it('does not throw when there is no current track', async () => {
        const wrapper = mount(Tv, {props: {party, state: state({current: null})}});
        expect(wrapper.text()).toContain('Song');

        mocks.handler?.(state({now: null, current: null, status: null}));
        await nextTick();
        expect(wrapper.text()).toContain('Nothing playing');
        wrapper.unmount();
    });

    it('reloads only the state prop after a realtime reconnect', () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        mocks.reconnect?.();
        expect(mocks.reload).toHaveBeenCalledWith({only: ['state']});
        wrapper.unmount();
    });

    it('renders the local QR code as an inline image with a descriptive alt and no external host', () => {
        const wrapper = mount(Tv, {props: {party, state: state({show_qrcode: true})}});
        const qr = wrapper.get('[data-test="tv-qr"]');

        expect(qr.attributes('src')).toMatch(/^data:image\/svg\+xml/);
        expect(qr.attributes('alt')).toBe('Scan to join P');
        expect(wrapper.html()).not.toContain('qrserver');
        wrapper.unmount();
    });

    it('scales the progress bar with a transform rather than width', () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        const fill = wrapper.get('[role="progressbar"] > div');

        expect(fill.attributes('style')).toContain('scaleX(0.2)');
        expect(fill.attributes('style')).not.toContain('width');
        wrapper.unmount();
    });

    it('puts a small QR with the join code in the header while a track plays', () => {
        const wrapper = mount(Tv, {props: {party, state: state({show_qrcode: true})}});

        expect(wrapper.get('.tv-join').text()).toContain('Scan or join with code');
        expect(wrapper.get('.tv-join [data-test="join-code"]').text()).toBe('ABC');
        expect(wrapper.find('[data-test="join-url"]').exists()).toBe(false);
        expect(wrapper.get('.tv-join [data-test="tv-qr"]').classes()).toContain('tv-qr-small');
        wrapper.unmount();
    });

    it('shows only the join code when the QR code is turned off', () => {
        const wrapper = mount(Tv, {props: {party, state: state({show_qrcode: false})}});

        expect(wrapper.get('.tv-join').text()).toContain('Join with code');
        expect(wrapper.find('[data-test="tv-qr"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('moves a large QR into the middle when nothing is playing and leaves only the party name in the header', () => {
        const wrapper = mount(Tv, {
            props: {party, state: state({now: null, current: null, status: null, show_qrcode: true})},
        });

        expect(wrapper.find('.tv-join').exists()).toBe(false);
        expect(wrapper.get('[data-test="nothing-playing"]').text()).toContain(
            'Scan to add the first track, or join with code',
        );
        expect(wrapper.get('[data-test="nothing-playing"] [data-test="tv-qr"]').classes()).toContain('tv-qr-large');
        expect(wrapper.get('[data-test="join-code"]').text()).toBe('ABC');
        expect(wrapper.find('[data-test="join-url"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('shows just the code when nothing is playing and the QR code is off', () => {
        const wrapper = mount(Tv, {
            props: {party, state: state({now: null, current: null, status: null, show_qrcode: false})},
        });

        expect(wrapper.find('[data-test="tv-qr"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="join-code"]').text()).toBe('ABC');
        wrapper.unmount();
    });

    it('freezes the progress at the last position when paused and labels it', async () => {
        const wrapper = mount(Tv, {props: {party, state: state({status: playing(false)})}});

        vi.advanceTimersByTime(20000);
        await nextTick();

        expect(wrapper.text()).toContain('0:10');
        expect(wrapper.get('[role="progressbar"] > div').attributes('style')).toContain('scaleX(0.05)');
        expect(wrapper.get('[data-test="paused"]').text()).toBe('Paused');
        wrapper.unmount();
    });

    it('shows Reconnecting over Paused, then says it is showing the last known track after 60 seconds', async () => {
        const wrapper = mount(Tv, {props: {party, state: state({status: playing(false)})}});

        mocks.connected!.value = false;
        await nextTick();
        expect(wrapper.get('[data-test="reconnecting"]').text()).toBe('Reconnecting…');
        expect(wrapper.find('[data-test="paused"]').exists()).toBe(false);
        expect(wrapper.get('[role="status"]').attributes('aria-live')).toBe('polite');

        vi.advanceTimersByTime(60000);
        await nextTick();
        expect(wrapper.get('[data-test="reconnecting"]').text()).toBe('Reconnecting… showing the last known track');

        mocks.connected!.value = true;
        await nextTick();
        expect(wrapper.find('[data-test="reconnecting"]').exists()).toBe(false);
        expect(vi.getTimerCount()).toBe(0);
        wrapper.unmount();
    });

    it('clears the stale timer when unmounted while disconnected', async () => {
        const wrapper = mount(Tv, {props: {party, state: state({status: playing(false)})}});
        mocks.connected!.value = false;
        await nextTick();
        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);
    });

    it('stops the bar at the track end while disconnected', async () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        mocks.connected!.value = false;

        vi.advanceTimersByTime(600000);
        await nextTick();

        expect(wrapper.text()).toContain('3:20');
        expect(wrapper.get('[role="progressbar"] > div').attributes('style')).toContain('scaleX(1)');
        wrapper.unmount();
    });

    it('shows the requester or From Fallback Playlist with a read-only score and no progress when the length is unknown', () => {
        const wrapper = mount(Tv, {
            props: {
                party,
                state: state({
                    current: {id: 1, provider_track_id: 't1', user: 'Ref', rating: -4},
                    status: {...playing(true), length: 0},
                }),
            },
        });

        expect(wrapper.get('[data-test="requested-by"]').text()).toBe('Requested by Ref');
        expect(wrapper.get('[data-test="tv-score"]').text()).toBe('-4');
        expect(wrapper.find('[data-test="progress-row"]').exists()).toBe(false);
        expect(wrapper.find('.tv-score button').exists()).toBe(false);
        wrapper.unmount();
    });

    it('renders a record tile with no album art and hides Up Next when nothing is queued', () => {
        const wrapper = mount(Tv, {props: {party, state: state({now: track({album: {name: 'Al', image_url: ''}})})}});

        expect(wrapper.find('img.tv-art').exists()).toBe(false);
        expect(wrapper.find('[data-test="art-blank"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="up-next"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('requests a screen wake lock on mount, re-requests when the tab is visible again, and releases on unmount', async () => {
        const release = vi.fn(() => Promise.resolve());
        const request = vi.fn(() =>
            Promise.resolve({release, addEventListener: vi.fn()} as unknown as WakeLockSentinel),
        );
        Object.defineProperty(navigator, 'wakeLock', {value: {request}, configurable: true});

        const wrapper = mount(Tv, {props: {party, state: state()}});
        await vi.advanceTimersByTimeAsync(0);
        expect(request).toHaveBeenCalledTimes(1);
        expect(request).toHaveBeenCalledWith('screen');

        wrapper.unmount();
        expect(release).toHaveBeenCalledTimes(1);

        const again = mount(Tv, {props: {party, state: state()}});
        await vi.advanceTimersByTimeAsync(0);
        expect(request).toHaveBeenCalledTimes(2);
        again.unmount();
    });

    it('re-requests the wake lock after it was released while the tab was hidden', async () => {
        let onRelease: () => void = () => undefined;
        const request = vi.fn(() =>
            Promise.resolve({
                release: vi.fn(() => Promise.resolve()),
                addEventListener: (_event: string, cb: () => void) => {
                    onRelease = cb;
                },
            } as unknown as WakeLockSentinel),
        );
        Object.defineProperty(navigator, 'wakeLock', {value: {request}, configurable: true});

        const wrapper = mount(Tv, {props: {party, state: state()}});
        await vi.advanceTimersByTimeAsync(0);
        onRelease();
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);

        expect(request).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('does not throw where the Wake Lock API is unavailable', () => {
        const wrapper = mount(Tv, {props: {party, state: state()}});
        wrapper.unmount();
    });
});

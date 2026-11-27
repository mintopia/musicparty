import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {nextTick} from 'vue';
import Tv from '@/pages/Parties/Tv.vue';

const mocks = vi.hoisted(() => ({
    reload: vi.fn(),
    channels: [] as string[],
    handler: null as null | ((payload: unknown) => void),
    reconnect: null as null | (() => void),
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
    fallback_name: 'Fallback Track',
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
    });
    afterEach(() => vi.useRealTimers());

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
        expect(wrapper.text()).toContain('There is nothing playing');
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
        expect(qr.attributes('alt')).toBe('Scan to join ABC');
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
});

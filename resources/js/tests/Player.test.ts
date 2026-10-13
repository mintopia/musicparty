import {flushPromises, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {nextTick} from 'vue';
import Player from '../components/Player.vue';

const echo = vi.hoisted(() => ({
    handler: null as null | ((payload: unknown) => void),
    reconnect: null as null | (() => void),
    channels: [] as string[],
}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        onReconnect: (cb: () => void) => void (echo.reconnect = cb),
        publicChannel: (name: string) => {
            echo.channels.push(name);
            return {listen: (_event: string, cb: (payload: unknown) => void) => void (echo.handler = cb)};
        },
    }),
}));

const track = {
    provider_track_id: 't1',
    name: 'Song',
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
};
const playing = {
    isPlaying: true,
    sampledAt: '2030-01-01T00:00:00Z',
    progressMs: 0,
    length: 200000,
};
const stateOf = (extra: Record<string, unknown> = {}) => ({
    now: track,
    next: null,
    current: {id: 1, provider_track_id: 't1', user: null, rating: 3},
    status: playing,
    ...extra,
});
const build = (state: unknown) =>
    mount(Player, {props: {code: 'ABC', initialstate: JSON.stringify(state)}});

describe('Player', () => {
    const get = vi.fn();

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-01T00:00:10Z'));
        get.mockReset();
        (window as unknown as {axios: unknown}).axios = {get, post: vi.fn()};
    });
    afterEach(() => vi.useRealTimers());

    it('restarts the progress timer after a pause', async () => {
        const wrapper = build(stateOf());
        expect(wrapper.text()).toContain('0:10');

        echo.handler?.(stateOf({status: {...playing, isPlaying: false}}));
        await nextTick();
        expect(wrapper.text()).not.toContain('0:10');

        echo.handler?.(stateOf());
        await nextTick();
        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(wrapper.text()).toContain('0:15');
    });

    it('clears the ticker on unmount', () => {
        const wrapper = build(stateOf());
        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('does not throw when current or album is missing', () => {
        const wrapper = build(stateOf({current: null, now: {...track, album: undefined}}));
        expect(wrapper.text()).toContain('Song');
    });

    it('refetches state after an Echo reconnect', async () => {
        const wrapper = build(stateOf());
        get.mockResolvedValue({data: {data: {status: stateOf({now: {...track, name: 'Fresh'}})}}});

        echo.reconnect?.();
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/api/v1/parties/ABC');
        expect(wrapper.text()).toContain('Fresh');
    });
});

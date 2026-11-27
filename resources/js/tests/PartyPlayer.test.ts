import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import PartyPlayer from '@/components/PartyPlayer.vue';
import type {LegacyPartyState} from '@/types';

const inertia = vi.hoisted(() => ({reload: vi.fn()}));
const echo = vi.hoisted(() => ({
    update: null as null | ((payload: unknown) => void),
    reconnect: null as null | (() => void),
}));

vi.mock('@inertiajs/vue3', () => ({router: inertia}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        onReconnect: (cb: () => void) => {
            echo.reconnect = cb;
        },
        publicChannel: () => ({
            listen: (_event: string, cb: (payload: unknown) => void) => {
                echo.update = cb;
            },
        }),
    }),
}));

const now = {
    provider_track_id: 't1',
    name: 'Song',
    artists: [{name: 'A'}],
    album: {name: 'Al', image_url: ''},
    length: 200000,
    score: 0,
    user: null,
    fallback_name: null,
    css_classes: '',
};

const playing = (sampledAt: string, progressMs = 0): LegacyPartyState => ({
    now,
    next: null,
    current: null,
    status: {isPlaying: true, sampledAt, progressMs, length: 200000},
});

const width = (wrapper: ReturnType<typeof mount>): string => wrapper.get('[role="progressbar"] > div').attributes('style') ?? '';

describe('PartyPlayer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-01T00:00:00Z'));
        inertia.reload.mockReset();
    });
    afterEach(() => vi.useRealTimers());

    it('advances the clock again after a pause and resume', async () => {
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')},
        });
        await vi.advanceTimersByTimeAsync(2000);
        expect(wrapper.text()).toContain('0:02');

        echo.update?.({...playing('2030-01-01T00:00:02Z'), status: {...playing('x').status, isPlaying: false}});
        await vi.advanceTimersByTimeAsync(5000);
        expect(vi.getTimerCount()).toBe(0);

        echo.update?.(playing('2030-01-01T00:00:07Z', 30000));
        await vi.advanceTimersByTimeAsync(3000);

        expect(wrapper.text()).toContain('0:33');
        expect(width(wrapper)).toContain('scaleX(0.165)');
    });

    it('clears its timer when unmounted', () => {
        const wrapper = mount(PartyPlayer, {props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')}});
        expect(vi.getTimerCount()).toBe(1);

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);
    });

    it('renders without a current track or album details', async () => {
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {now, next: null, current: null, status: null}},
        });

        expect(wrapper.find('[data-test="rate-control"]').exists()).toBe(false);

        echo.update?.({now: {name: 'Bare'}, next: null, current: null, status: null});
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="now-name"]').text()).toBe('Bare');
    });

    it('reloads the player state after a reconnect', () => {
        mount(PartyPlayer, {props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')}});

        echo.reconnect?.();

        expect(inertia.reload).toHaveBeenCalledWith({only: ['playerState']});
    });

    it('follows a refreshed initial state', async () => {
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {now: null, next: null, current: null, status: null}},
        });
        expect(wrapper.find('[data-test="nothing-playing"]').exists()).toBe(true);

        await wrapper.setProps({initialState: playing('2030-01-01T00:00:00Z')});

        expect(wrapper.get('[data-test="now-name"]').text()).toBe('Song');
    });
});

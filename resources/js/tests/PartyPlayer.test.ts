import {flushPromises, mount} from '@vue/test-utils';
import axios, {AxiosError, CanceledError} from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {ref} from 'vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import type {LegacyPartyState, MyRating} from '@/types';

const inertia = vi.hoisted(() => ({reload: vi.fn()}));
const echo = vi.hoisted(() => ({
    update: null as null | ((payload: unknown) => void),
    reconnect: null as null | (() => void),
    connected: null as null | {value: boolean},
}));

vi.mock('@inertiajs/vue3', () => ({router: inertia, usePage: () => ({props: {flash: {}}})}));
vi.mock('@/composables/useEcho', () => ({
    useEcho: () => ({
        onReconnect: (cb: () => void) => {
            echo.reconnect = cb;
        },
        trackConnection: () => echo.connected,
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
    album: {name: 'Al', image_url: 'https://img.test/a.jpg'},
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

const rated = (rating = 3, extra: Partial<LegacyPartyState> = {}): LegacyPartyState => ({
    ...playing('2030-01-01T00:00:00Z'),
    current: {id: 7, provider_track_id: 't1', user: 'Ref Member', rating},
    ...extra,
});

const width = (wrapper: ReturnType<typeof mount>): string =>
    wrapper.get('[role="progressbar"] > div').attributes('style') ?? '';

const originalAdapter = axios.defaults.adapter;
let requests: {data: string; signal?: AbortSignal; settle: (outcome: 'ok' | number) => void}[] = [];

function installAdapter(): void {
    requests = [];
    axios.defaults.adapter = (config) =>
        new Promise((resolve, reject) => {
            const entry = {
                data: String(config.data),
                signal: config.signal as AbortSignal | undefined,
                settle: (outcome: 'ok' | number) => {
                    if (outcome === 'ok') {
                        resolve({data: {}, status: 200, statusText: 'OK', headers: {}, config} as never);
                        return;
                    }
                    reject(
                        new AxiosError('failed', 'ERR_BAD_REQUEST', config, null, {
                            status: outcome,
                            data: {},
                            statusText: '',
                            headers: {},
                            config,
                        } as never),
                    );
                },
            };
            (config.signal as AbortSignal | undefined)?.addEventListener('abort', () =>
                reject(new CanceledError(undefined, config)),
            );
            requests.push(entry);
        });
}

describe('PartyPlayer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-01T00:00:00Z'));
        inertia.reload.mockReset();
        echo.connected = ref(true);
        installAdapter();
    });
    afterEach(() => {
        vi.useRealTimers();
        axios.defaults.adapter = originalAdapter;
    });

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

    it('freezes the bar and times at the last position while paused, with a Paused pill', async () => {
        const state = playing('2030-01-01T00:00:00Z', 30000);
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {...state, status: {...state.status!, isPlaying: false}}},
        });

        await vi.advanceTimersByTimeAsync(10000);

        expect(wrapper.text()).toContain('0:30');
        expect(wrapper.text()).toContain('3:20');
        expect(width(wrapper)).toContain('scaleX(0.15)');
        expect(wrapper.get('[data-test="paused"]').text()).toBe('Paused');
        expect(wrapper.get('.player-bar').classes()).toContain('paused');
    });

    it('shows a polite Reconnecting pill that beats Paused, and drops it once connected', async () => {
        const state = playing('2030-01-01T00:00:00Z', 30000);
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {...state, status: {...state.status!, isPlaying: false}}},
        });
        expect(wrapper.find('[data-test="reconnecting"]').exists()).toBe(false);

        echo.connected!.value = false;
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="reconnecting"]').text()).toBe('Reconnecting…');
        expect(wrapper.find('[data-test="paused"]').exists()).toBe(false);
        expect(wrapper.get('[role="status"]').attributes('aria-live')).toBe('polite');

        echo.connected!.value = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="reconnecting"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="paused"]').exists()).toBe(true);
    });

    it('renders a vinyl tile instead of a broken image when there is no album art', () => {
        const wrapper = mount(PartyPlayer, {
            props: {
                code: 'ABC',
                initialState: {...playing('2030-01-01T00:00:00Z'), now: {...now, album: {name: 'Al', image_url: ''}}},
            },
        });

        expect(wrapper.find('img.player-art').exists()).toBe(false);
        expect(wrapper.find('[data-test="art-blank"] .ti-vinyl').exists()).toBe(true);
    });

    it('swaps in the tile when the album art fails to load', async () => {
        const wrapper = mount(PartyPlayer, {props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')}});

        await wrapper.get('img.player-art').trigger('error');

        expect(wrapper.find('img.player-art').exists()).toBe(false);
        expect(wrapper.find('[data-test="art-blank"]').exists()).toBe(true);
    });

    it('clamps long text with title tooltips and hides the progress row when the length is unknown', () => {
        const wrapper = mount(PartyPlayer, {
            props: {
                code: 'ABC',
                initialState: {
                    ...playing('2030-01-01T00:00:00Z'),
                    now: {...now, name: 'A very long title'},
                    status: {isPlaying: true, sampledAt: '2030-01-01T00:00:00Z', progressMs: 0, length: 0},
                },
            },
        });

        expect(wrapper.get('[data-test="now-name"]').attributes('title')).toBe('A very long title');
        expect(wrapper.find('[data-test="progress-row"]').exists()).toBe(false);
        expect(wrapper.find('[role="progressbar"]').exists()).toBe(false);
    });

    it('shows requested-by or Fallback Track next to the rating', () => {
        const requested = mount(PartyPlayer, {props: {code: 'ABC', initialState: rated()}});
        expect(requested.get('[data-test="requested-by"]').text()).toBe('Requested by Ref Member');

        const fallback = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: rated(0, {current: {id: 7, provider_track_id: 't1', user: null}})},
        });
        expect(fallback.get('[data-test="requested-by"]').text()).toBe('Fallback Track');
    });

    it('folds Up Next into the band with its decoration tag and accent, and no separate card', () => {
        const next = {
            ...now,
            name: 'Miracle',
            length: 250000,
            decoration: {label: 'Boosted', accent: '#ff0000', class: '', displayName: null},
        };
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {...playing('2030-01-01T00:00:00Z'), next} as never},
        });

        const line = wrapper.get('[data-test="up-next"]');
        expect(line.text()).toContain('Up next');
        expect(line.text()).toContain('Miracle');
        expect(line.text()).toContain('4:10');
        expect(line.get('[data-test="decoration-label"]').text()).toBe('Boosted');
        expect(line.get('[data-test="decoration-label"]').attributes('style')).toContain('background-color');
    });

    it('says nothing is queued when there is no next track', () => {
        const wrapper = mount(PartyPlayer, {props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')}});

        expect(wrapper.get('[data-test="nothing-queued"]').text()).toBe('Nothing queued, search to add a track');
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

    it('shows the nothing playing copy at the same band height', () => {
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {now: null, next: null, current: null, status: null}},
        });
        const empty = wrapper.get('[data-test="nothing-playing"]');

        expect(empty.text()).toContain('Nothing playing');
        expect(empty.text()).toContain('Search for a track to get the party started.');
        expect(wrapper.find('[data-test="paused"]').exists()).toBe(false);
    });

    it('reloads the player state and own rating after a reconnect', () => {
        mount(PartyPlayer, {props: {code: 'ABC', initialState: playing('2030-01-01T00:00:00Z')}});

        echo.reconnect?.();

        expect(inertia.reload).toHaveBeenCalledWith({only: ['playerState', 'myRating']});
    });

    it('follows a refreshed initial state', async () => {
        const wrapper = mount(PartyPlayer, {
            props: {code: 'ABC', initialState: {now: null, next: null, current: null, status: null}},
        });
        expect(wrapper.find('[data-test="nothing-playing"]').exists()).toBe(true);

        await wrapper.setProps({initialState: playing('2030-01-01T00:00:00Z')});

        expect(wrapper.get('[data-test="now-name"]').text()).toBe('Song');
    });

    describe('rating', () => {
        const mountRated = (myRating?: MyRating | null) =>
            mount(PartyPlayer, {props: {code: 'ABC', initialState: rated(), myRating}});
        const up = (wrapper: ReturnType<typeof mount>) => wrapper.get('[data-test="rate-up"]');
        const down = (wrapper: ReturnType<typeof mount>) => wrapper.get('[data-test="rate-down"]');
        const count = (wrapper: ReturnType<typeof mount>) => wrapper.get('[data-test="rating"]').text();

        it('orders the control up, count, down and presses the thumb matching the caller own vote', () => {
            const wrapper = mountRated({playedSongId: 7, value: 1});
            const order = wrapper.get('[data-test="rate-control"]').element.children;

            expect(order[0]?.getAttribute('aria-label')).toBe('Rate up');
            expect(order[1]?.getAttribute('data-test')).toBe('rating');
            expect(order[2]?.getAttribute('aria-label')).toBe('Rate down');
            expect(up(wrapper).attributes('aria-pressed')).toBe('true');
            expect(down(wrapper).attributes('aria-pressed')).toBe('false');
        });

        it('ignores an own vote recorded against a previous track and when myRating is absent', () => {
            expect(up(mountRated({playedSongId: 6, value: 1})).attributes('aria-pressed')).toBe('false');
            expect(up(mountRated()).attributes('aria-pressed')).toBe('false');
            expect(up(mountRated(null)).attributes('aria-pressed')).toBe('false');
        });

        it('updates the pressed state and count at once, sends the value, and never disables the buttons', async () => {
            const wrapper = mountRated(null);

            await up(wrapper).trigger('click');

            expect(up(wrapper).attributes('aria-pressed')).toBe('true');
            expect(count(wrapper)).toBe('4');
            expect(requests).toHaveLength(1);
            expect(requests[0]?.data).toBe('{"rating":1}');
            expect(up(wrapper).attributes('disabled')).toBeUndefined();
            expect(down(wrapper).attributes('disabled')).toBeUndefined();
        });

        it('switches a vote from up to down and clears it when the pressed thumb is clicked again', async () => {
            const wrapper = mountRated({playedSongId: 7, value: 1});

            await down(wrapper).trigger('click');
            expect(count(wrapper)).toBe('1');
            expect(down(wrapper).attributes('aria-pressed')).toBe('true');
            expect(requests[0]?.data).toBe('{"rating":-1}');

            await down(wrapper).trigger('click');
            expect(count(wrapper)).toBe('2');
            expect(down(wrapper).attributes('aria-pressed')).toBe('false');
            expect(requests[1]?.data).toBe('{"rating":0}');
        });

        it('lets the latest click win by aborting the previous in-flight request', async () => {
            const wrapper = mountRated(null);

            await up(wrapper).trigger('click');
            await down(wrapper).trigger('click');
            await flushPromises();

            expect(requests[0]?.signal?.aborted).toBe(true);
            expect(requests[1]?.signal?.aborted).toBe(false);
            expect(count(wrapper)).toBe('2');
            expect(wrapper.find('[data-test="rating-notice"]').exists()).toBe(false);

            requests[1]?.settle('ok');
            await flushPromises();
            expect(down(wrapper).attributes('aria-pressed')).toBe('true');
        });

        it.each([
            [429, 'Slow down, try again in a moment'],
            [400, 'That track has already finished'],
            [500, "Couldn't save your rating."],
        ])('rolls back and shows a toast that auto-dismisses after a %i', async (status, message) => {
            const wrapper = mountRated({playedSongId: 7, value: 1});

            await down(wrapper).trigger('click');
            expect(count(wrapper)).toBe('1');

            requests[0]?.settle(status);
            await flushPromises();

            expect(count(wrapper)).toBe('3');
            expect(up(wrapper).attributes('aria-pressed')).toBe('true');
            expect(down(wrapper).attributes('aria-pressed')).toBe('false');
            expect(wrapper.get('[data-test="rating-notice"]').text()).toContain(message);

            await vi.advanceTimersByTimeAsync(4000);
            expect(wrapper.find('[data-test="rating-notice"]').exists()).toBe(false);
        });

        it('rolls a chain of clicks back to the last confirmed state when the latest fails', async () => {
            const wrapper = mountRated(null);

            await up(wrapper).trigger('click');
            await down(wrapper).trigger('click');
            requests[1]?.settle(500);
            await flushPromises();

            expect(count(wrapper)).toBe('3');
            expect(up(wrapper).attributes('aria-pressed')).toBe('false');
            expect(down(wrapper).attributes('aria-pressed')).toBe('false');
        });

        it('lets the next broadcast replace the optimistic count and drops the vote when the track changes', async () => {
            const wrapper = mountRated(null);
            await up(wrapper).trigger('click');
            requests[0]?.settle('ok');
            await flushPromises();

            echo.update?.(rated(9));
            await wrapper.vm.$nextTick();
            expect(count(wrapper)).toBe('9');
            expect(up(wrapper).attributes('aria-pressed')).toBe('true');

            echo.update?.({...rated(2), current: {id: 8, provider_track_id: 't1', user: null, rating: 2}});
            await wrapper.vm.$nextTick();
            expect(count(wrapper)).toBe('2');
            expect(up(wrapper).attributes('aria-pressed')).toBe('false');
        });
    });
});

import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import WebPlayer from '@/pages/Parties/WebPlayer.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
    router: {
        on: (_event: string, cb: (e: {preventDefault: () => void}) => void) => {
            echo.navGuard = cb;
            return () => {};
        },
    },
}));

vi.mock('@/layouts/BareLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));

vi.mock('axios', () => ({default: {get: vi.fn(), post: vi.fn()}}));

const echo = vi.hoisted(() => ({
    channels: [] as string[],
    handlers: {} as Record<string, (payload: never) => void>,
    whispers: [] as Array<[string, Record<string, unknown>]>,
    whisperHandlers: {} as Record<string, (payload: never) => void>,
    navGuard: null as null | ((e: {preventDefault: () => void}) => void),
}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => {
        const channel = {
            listen: (event: string, cb: (payload: never) => void) => {
                echo.handlers[event] = cb;
                return channel;
            },
            listenForWhisper: (event: string, cb: (payload: never) => void) => {
                echo.whisperHandlers[event] = cb;
                return channel;
            },
            whisper: (event: string, data: Record<string, unknown>) => {
                echo.whispers.push([event, data]);
                return channel;
            },
        };

        return {
            privateChannel: (name: string) => {
                echo.channels.push(name);
                return channel;
            },
        };
    },
}));

type Listener = (payload: unknown) => void;
const spotify = {
    listeners: {} as Record<string, Listener>,
    getOAuthToken: null as null | ((cb: (token: string) => void) => void),
    options: null as null | Record<string, unknown>,
    connect: vi.fn(),
    disconnect: vi.fn(),
    resume: vi.fn(),
    pause: vi.fn(),
    nextTrack: vi.fn(),
    previousTrack: vi.fn(),
    seek: vi.fn(),
};

class FakePlayer {
    constructor(options: Record<string, unknown> & {getOAuthToken: (cb: (token: string) => void) => void}) {
        spotify.options = options;
        spotify.getOAuthToken = options.getOAuthToken;
    }
    addListener(event: string, cb: Listener): boolean {
        spotify.listeners[event] = cb;
        return true;
    }
    connect = spotify.connect;
    disconnect = spotify.disconnect;
    activateElement = vi.fn();
    togglePlay = vi.fn();
    resume = spotify.resume;
    pause = spotify.pause;
    nextTrack = spotify.nextTrack;
    previousTrack = spotify.previousTrack;
    seek = spotify.seek;
}

function currentToken(): string {
    let token = '';
    spotify.getOAuthToken?.((value) => (token = value));
    return token;
}

const mounted: Array<{unmount: () => void}> = [];

async function mountPlayer() {
    const wrapper = mount(WebPlayer, {
        props: {party: {code: 'ABC', name: 'Friday'}, tokenUrl: '/token', deviceName: 'Music Party'},
    });
    await flushPromises();
    (window as unknown as {onSpotifyWebPlaybackSDKReady: () => void}).onSpotifyWebPlaybackSDKReady();
    await flushPromises();
    mounted.push(wrapper);

    return wrapper;
}

function state(overrides: Record<string, unknown> = {}) {
    return {
        paused: false,
        position: 1200,
        duration: 180000,
        timestamp: 1700000000,
        shuffle: true,
        repeat_mode: 2,
        track_window: {current_track: {uri: 'spotify:track:1', name: 'Song', artists: [{name: 'A'}, {name: 'B'}]}},
        ...overrides,
    };
}

describe('WebPlayer', () => {
    beforeEach(() => {
        echo.channels.length = 0;
        echo.whispers.length = 0;
        echo.handlers = {};
        echo.whisperHandlers = {};
        echo.navGuard = null;
        vi.clearAllMocks();
        spotify.listeners = {};
        spotify.getOAuthToken = null;
        vi.mocked(axios.get)
            .mockReset()
            .mockResolvedValue({data: {accessToken: 'tok1'}});
        vi.mocked(axios.post).mockReset();
        (window as unknown as {Spotify: unknown}).Spotify = {Player: FakePlayer};
    });

    afterEach(() => {
        mounted.splice(0).forEach((wrapper) => wrapper.unmount());
        document.querySelectorAll('script[src*="sdk.scdn.co"]').forEach((el) => el.remove());
    });

    it('fetches the token from tokenUrl, connects, and uses refreshed tokens', async () => {
        await mountPlayer();

        expect(axios.get).toHaveBeenCalledWith('/token');
        expect(echo.channels).toEqual(['webplayer.ABC']);
        expect(spotify.options).toMatchObject({name: 'Music Party', volume: 0.5});
        expect(spotify.connect).toHaveBeenCalled();
        expect(currentToken()).toBe('tok1');

        echo.handlers['.WebPlayerTokenRefreshed']({accessToken: 'tok2'} as never);

        expect(currentToken()).toBe('tok2');
    });

    it('whispers exactly one mapped state per state change and makes no other requests', async () => {
        const wrapper = await mountPlayer();
        spotify.listeners.ready({device_id: 'dev1'});
        spotify.listeners.player_state_changed(state());
        await flushPromises();

        expect(echo.whispers.filter(([event]) => event === 'state')).toEqual([
            [
                'state',
                {
                    uri: 'spotify:track:1',
                    paused: false,
                    position: 1200,
                    duration: 180000,
                    timestamp: 1700000000,
                    shuffle: true,
                    repeat: 'track',
                    deviceId: 'dev1',
                },
            ],
        ]);
        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="track-name"]').text()).toBe('Song');
        expect(wrapper.get('[data-test="track-artists"]').text()).toBe('A, B');
    });

    it('ignores a null state', async () => {
        await mountPlayer();
        spotify.listeners.player_state_changed(null);

        expect(echo.whispers).toHaveLength(0);
    });

    it('shows ready and error statuses', async () => {
        const wrapper = await mountPlayer();
        spotify.listeners.ready({device_id: 'dev1'});
        await flushPromises();
        expect(wrapper.get('[data-test="status"]').text()).toBe('Ready');

        spotify.listeners.account_error({message: 'premium required'});
        await flushPromises();
        expect(wrapper.get('[data-test="error"]').text()).toBe('premium required');
    });

    describe('recovery', () => {
        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        it('refreshes the token and reconnects after not_ready and authentication_error', async () => {
            await mountPlayer();
            spotify.connect.mockClear();
            vi.mocked(axios.get).mockResolvedValue({data: {accessToken: 'tok2'}});

            spotify.listeners.not_ready({device_id: 'dev1'});
            await vi.advanceTimersByTimeAsync(1000);

            expect(axios.get).toHaveBeenCalledTimes(2);
            expect(currentToken()).toBe('tok2');
            expect(spotify.disconnect).toHaveBeenCalled();
            expect(spotify.connect).toHaveBeenCalledTimes(1);

            vi.mocked(axios.get).mockResolvedValue({data: {accessToken: 'tok3'}});
            spotify.listeners.authentication_error({message: 'expired'});
            await vi.advanceTimersByTimeAsync(2000);

            expect(currentToken()).toBe('tok3');
            expect(spotify.connect).toHaveBeenCalledTimes(2);
        });

        it('backs off when the token refresh fails', async () => {
            await mountPlayer();
            spotify.connect.mockClear();
            vi.mocked(axios.get).mockRejectedValue(new Error('down'));

            spotify.listeners.not_ready({device_id: 'dev1'});
            await vi.advanceTimersByTimeAsync(1000);
            expect(spotify.connect).not.toHaveBeenCalled();

            vi.mocked(axios.get).mockResolvedValue({data: {accessToken: 'tok2'}});
            await vi.advanceTimersByTimeAsync(2000);
            expect(spotify.connect).toHaveBeenCalledTimes(1);
        });

        it('shows a resume control when autoplay is blocked and hides it once playing', async () => {
            const wrapper = await mountPlayer();
            expect(wrapper.find('[data-test="resume"]').exists()).toBe(false);

            spotify.listeners.autoplay_failed({});
            await flushPromises();
            expect(wrapper.get('[data-test="resume"]').text()).toBe('Click to resume');

            await wrapper.get('[data-test="resume"]').trigger('click');
            expect(spotify.resume).toHaveBeenCalled();
            expect(wrapper.find('[data-test="resume"]').exists()).toBe(false);
        });
    });

    it('maps channel transport commands onto the SDK', async () => {
        await mountPlayer();

        echo.handlers['.play']({} as never);
        echo.handlers['.pause']({} as never);
        echo.handlers['.next']({} as never);
        echo.handlers['.skip_prev']({} as never);
        echo.handlers['.seek']({position_ms: 5000} as never);

        expect(spotify.resume).toHaveBeenCalled();
        expect(spotify.pause).toHaveBeenCalled();
        expect(spotify.nextTrack).toHaveBeenCalled();
        expect(spotify.previousTrack).toHaveBeenCalled();
        expect(spotify.seek).toHaveBeenCalledWith(5000);
    });

    it('controls the SDK from on-page buttons and the seek bar', async () => {
        const wrapper = await mountPlayer();

        await wrapper.get('[data-test="next"]').trigger('click');
        await wrapper.get('[data-test="previous"]').trigger('click');
        const seek = wrapper.get('[data-test="seek"]');
        (seek.element as HTMLInputElement).value = '0';
        await seek.trigger('change');

        expect(spotify.nextTrack).toHaveBeenCalled();
        expect(spotify.previousTrack).toHaveBeenCalled();
        expect(spotify.seek).toHaveBeenCalledWith(0);
    });

    it('warns before unload and navigation only while it is the active Player', async () => {
        await mountPlayer();
        const unload = () => {
            const event = new Event('beforeunload', {cancelable: true});
            window.dispatchEvent(event);
            return event.defaultPrevented;
        };
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const navigate = () => {
            const preventDefault = vi.fn();
            echo.navGuard?.({preventDefault});
            return preventDefault;
        };

        expect(unload()).toBe(false);
        expect(navigate()).not.toHaveBeenCalled();
        expect(confirm).not.toHaveBeenCalled();

        spotify.listeners.ready({device_id: 'dev1'});
        expect(unload()).toBe(true);
        expect(navigate()).toHaveBeenCalled();

        confirm.mockReturnValue(true);
        expect(navigate()).not.toHaveBeenCalled();

        echo.whisperHandlers.claim({instanceId: 'zzz', claimedAt: Date.now() + 1000} as never);
        expect(unload()).toBe(false);
        confirm.mockRestore();
    });

    it('claims on ready, stands down for a newer claim and stops sending state', async () => {
        const wrapper = await mountPlayer();
        spotify.listeners.ready({device_id: 'dev1'});
        expect(echo.whispers.map(([event]) => event)).toEqual(['claim']);

        echo.whisperHandlers.claim({instanceId: 'zzz', claimedAt: Date.now() + 1000} as never);
        await flushPromises();

        expect(spotify.disconnect).toHaveBeenCalled();
        expect(wrapper.get('[data-test="status"]').text()).toBe('Taken over elsewhere');
        expect(wrapper.find('[data-test="taken-over"]').exists()).toBe(true);

        spotify.listeners.player_state_changed(state());
        expect(echo.whispers.map(([event]) => event)).toEqual(['claim']);
    });

    it('re-asserts its claim when an older instance claims', async () => {
        await mountPlayer();
        spotify.listeners.ready({device_id: 'dev1'});
        echo.whispers.length = 0;

        echo.whisperHandlers.claim({instanceId: 'old', claimedAt: 1} as never);

        expect(echo.whispers.map(([event]) => event)).toEqual(['claim']);
        expect(spotify.disconnect).not.toHaveBeenCalled();
    });
});

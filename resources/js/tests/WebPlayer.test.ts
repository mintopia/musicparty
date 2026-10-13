import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import WebPlayer from '@/pages/Parties/WebPlayer.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    usePage: () => ({props: {flash: {}, auth: {user: null}}}),
}));

vi.mock('@/layouts/BareLayout.vue', () => ({default: {template: '<div><slot /></div>'}}));

vi.mock('axios', () => ({default: {get: vi.fn(), post: vi.fn()}}));

const echo = vi.hoisted(() => ({
    channels: [] as string[],
    handlers: {} as Record<string, (payload: never) => void>,
    whispers: [] as Array<[string, Record<string, unknown>]>,
}));

vi.mock('@/composables/useEcho', () => ({
    useEcho: () => {
        const channel = {
            listen: (event: string, cb: (payload: never) => void) => {
                echo.handlers[event] = cb;
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
}

function currentToken(): string {
    let token = '';
    spotify.getOAuthToken?.((value) => (token = value));
    return token;
}

async function mountPlayer() {
    const wrapper = mount(WebPlayer, {
        props: {party: {code: 'ABC', name: 'Friday'}, tokenUrl: '/token', deviceName: 'Music Party'},
    });
    await flushPromises();
    (window as unknown as {onSpotifyWebPlaybackSDKReady: () => void}).onSpotifyWebPlaybackSDKReady();
    await flushPromises();

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
        spotify.listeners = {};
        spotify.getOAuthToken = null;
        vi.mocked(axios.get)
            .mockReset()
            .mockResolvedValue({data: {accessToken: 'tok1'}});
        vi.mocked(axios.post).mockReset();
        (window as unknown as {Spotify: unknown}).Spotify = {Player: FakePlayer};
    });

    afterEach(() => {
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

        expect(echo.whispers).toEqual([
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

        spotify.listeners.authentication_error({message: 'bad token'});
        await flushPromises();
        expect(wrapper.get('[data-test="error"]').text()).toBe('bad token');
    });
});

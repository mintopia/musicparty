import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: {template: '<span />'},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
}));

import BrowserPlayer from '../pages/Party/BrowserPlayer.vue';

const props = {party: {code: 'ABCD', name: 'LAN'}, accessToken: 'secret-token', error: null, leadSeconds: 15, channel: 'party.ABCD.browser-player'};

let listeners;
let echoHandlers;
let fetchMock;
let mounted;

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

afterEach(() => {
    mounted?.unmount();
    mounted = null;
});

beforeEach(() => {
    listeners = {};
    echoHandlers = {};
    window.Spotify = {
        Player: class {
            addListener(name, callback) { listeners[name] = callback; }
            connect() { listeners.ready?.({device_id: 'dev-1'}); return Promise.resolve(true); }
            disconnect() {}
        },
    };
    window.Echo = {
        private: vi.fn(() => ({listen: (event, callback) => { echoHandlers[event] = callback; }})),
        leave: vi.fn(),
    };
    fetchMock = vi.fn(() => Promise.resolve({ok: true, json: () => Promise.resolve({})}));
    vi.stubGlobal('fetch', fetchMock);
    navigator.sendBeacon = vi.fn();
    localStorage.clear();
    sessionStorage.clear();
});

const startPlayer = async () => {
    const wrapper = mount(BrowserPlayer, {props});
    mounted = wrapper;
    await wrapper.get('[data-testid=player-start]').trigger('click');
    await flush();
    return wrapper;
};

describe('BrowserPlayer', () => {
    it('shows the error and no start button when the token is unavailable', () => {
        const wrapper = mount(BrowserPlayer, {props: {...props, accessToken: null, error: 'Link your account'}});
        expect(wrapper.get('[data-testid=player-error]').text()).toBe('Link your account');
        expect(wrapper.find('[data-testid=player-start]').exists()).toBe(false);
    });

    it('claims the player role and registers the device on start', async () => {
        const wrapper = await startPlayer();
        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe('/parties/ABCD/player/claim');
        expect(JSON.parse(options.body).tab_id).toBeTruthy();
        expect(window.Echo.private).toHaveBeenCalledWith('party.ABCD.browser-player');
        expect(wrapper.get('[data-testid=player-status]').text()).toContain('15 seconds');
    });

    it('does not start the SDK when another tab holds the role', async () => {
        fetchMock.mockImplementationOnce(() => Promise.resolve({ok: false, json: () => Promise.resolve({message: 'Another tab is the player'})}));
        const wrapper = await startPlayer();
        expect(wrapper.get('[data-testid=player-status]').text()).toBe('Another tab is the player');
        expect(window.Echo.private).not.toHaveBeenCalled();
    });

    it('plays the track handed over by a command on this device', async () => {
        await startPlayer();
        echoHandlers['.browser-player.command']({action: 'play', provider_id: 'spotify', track_id: 'abc'});
        const call = fetchMock.mock.calls.find(([url]) => url.startsWith('https://api.spotify.com/v1/me/player/play'));
        expect(call[0]).toContain('device_id=dev-1');
        expect(call[1].headers.Authorization).toBe('Bearer secret-token');
        expect(JSON.parse(call[1].body)).toEqual({uris: ['spotify:track:abc']});
    });

    it('reports player state changes', async () => {
        await startPlayer();
        listeners.player_state_changed({paused: false, position: 1234, duration: 200000, track_window: {current_track: {id: 'abc', name: 'T', artists: []}}});
        const call = fetchMock.mock.calls.find(([url]) => url === '/parties/ABCD/player/state');
        expect(JSON.parse(call[1].body)).toMatchObject({status: 'playing', track_id: 'abc', position_ms: 1234, duration_ms: 200000});
    });

    it('releases the role when the page is hidden', async () => {
        await startPlayer();
        window.dispatchEvent(new Event('pagehide'));
        expect(navigator.sendBeacon).toHaveBeenCalled();
        expect(navigator.sendBeacon.mock.calls[0][0]).toBe('/parties/ABCD/player/release');
    });

    it('never writes the token to browser storage', async () => {
        await startPlayer();
        listeners.player_state_changed({paused: false, position: 0, duration: 1, track_window: {current_track: {id: 'abc'}}});
        expect(JSON.stringify({...localStorage})).not.toContain('secret-token');
        expect(JSON.stringify({...sessionStorage})).not.toContain('secret-token');
        expect(fetchMock.mock.calls.filter(([url]) => url.startsWith('/parties')).every(([, options]) => !options.body.includes('secret-token'))).toBe(true);
    });
});

import {onBeforeUnmount, ref} from 'vue';

const SDK_URL = 'https://sdk.scdn.co/spotify-player.js';
const REPORT_INTERVAL_MS = 5000;
const HEARTBEAT_INTERVAL_MS = 20000;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const loadSdk = () => new Promise((resolve) => {
    if (window.Spotify) {
        resolve();
        return;
    }
    window.onSpotifyWebPlaybackSDKReady = resolve;
    const script = document.createElement('script');
    script.src = SDK_URL;
    document.head.appendChild(script);
});

export function useBrowserPlayer({partyCode, accessToken, channel}) {
    const status = ref('idle');
    const message = ref(null);
    const track = ref(null);
    const positionMs = ref(0);
    const durationMs = ref(0);
    const tabId = crypto.randomUUID();
    const base = `/parties/${partyCode}/player`;
    let player = null;
    let deviceId = null;
    let timer = null;
    let heartbeat = null;
    let lastState = null;

    const post = (path, body) => fetch(`${base}/${path}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken()},
        body: JSON.stringify({tab_id: tabId, ...body}),
    });

    const report = (state) => {
        const item = state?.track_window?.current_track;
        const body = {
            status: !state || (state.paused && state.position === 0 && !item) ? 'stopped' : state.paused ? 'paused' : 'playing',
            track_id: item?.id ?? null,
            position_ms: Math.round(state?.position ?? 0),
            duration_ms: state?.duration ? Math.round(state.duration) : null,
        };

        return post('state', body);
    };

    const onStateChanged = (state) => {
        lastState = state;
        track.value = state?.track_window?.current_track ?? null;
        positionMs.value = state?.position ?? 0;
        durationMs.value = state?.duration ?? 0;
        status.value = state && !state.paused ? 'playing' : 'ready';
        report(state);
    };

    const playTrack = async (trackId) => {
        await fetch(`https://api.spotify.com/v1/me/player/play?device_id=${encodeURIComponent(deviceId)}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', Authorization: `Bearer ${accessToken}`},
            body: JSON.stringify({uris: [`spotify:track:${trackId}`]}),
        });
    };

    const release = () => {
        const body = new Blob([JSON.stringify({tab_id: tabId, _token: csrfToken()})], {type: 'application/json'});
        navigator.sendBeacon(`${base}/release`, body);
    };

    const start = async () => {
        status.value = 'connecting';
        const claim = await post('claim', {});

        if (!claim.ok) {
            status.value = 'blocked';
            message.value = (await claim.json().catch(() => ({}))).message ?? 'Another tab is the player.';
            return;
        }

        await loadSdk();
        player = new window.Spotify.Player({name: 'Music Party', getOAuthToken: (callback) => callback(accessToken), volume: 0.8});
        player.addListener('ready', ({device_id: id}) => {
            deviceId = id;
            status.value = 'ready';
        });
        player.addListener('player_state_changed', onStateChanged);
        player.addListener('authentication_error', ({message: text}) => {
            status.value = 'error';
            message.value = text;
        });
        player.addListener('account_error', ({message: text}) => {
            status.value = 'error';
            message.value = text;
        });
        window.Echo.private(channel).listen('.browser-player.command', (command) => {
            if (command.action === 'play' && deviceId !== null) {
                playTrack(command.track_id);
            }
        });
        window.addEventListener('pagehide', release);
        timer = setInterval(() => {
            if (lastState && !lastState.paused) {
                report({...lastState, position: lastState.position + REPORT_INTERVAL_MS});
            }
        }, REPORT_INTERVAL_MS);
        heartbeat = setInterval(async () => {
            const response = await post('claim', {});

            if (!response.ok) {
                status.value = 'blocked';
                message.value = (await response.json().catch(() => ({}))).message ?? 'Another tab is the player.';
            }
        }, HEARTBEAT_INTERVAL_MS);
        await player.connect();
        await player.activateElement?.();
    };

    const stop = () => {
        clearInterval(timer);
        clearInterval(heartbeat);
        window.removeEventListener('pagehide', release);
        window.Echo?.leave(channel);
        player?.disconnect();
        release();
    };

    onBeforeUnmount(() => {
        if (player) {
            stop();
        }
    });

    return {status, message, track, positionMs, durationMs, tabId, start, stop};
}

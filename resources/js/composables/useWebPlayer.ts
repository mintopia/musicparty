import {router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import {useTicker} from '@/composables/useProgress';
import {useTransportShortcuts} from '@/composables/useTransportShortcuts';
import type {SpotifyGlobal, SpotifyPlayer, SpotifyState, SpotifyTrack, WhisperChannel} from '@/lib/spotifyPlayer';
import type {PlayerErrorKind} from '@/composables/useWebPlayerChecks';

const SDK_URL = 'https://sdk.scdn.co/spotify-player.js';
const REPEAT_MODES = ['off', 'context', 'track'] as const;
const MAX_RECONNECT_DELAY_MS = 30000;
const LEAVE_WARNING = 'This is the active Web Player for the Party. Leaving will stop playback.';

export type WebPlayerOptions = {
    partyCode: string;
    tokenUrl: string;
    activateUrl: string;
    deviceName: string;
};

export function useWebPlayer(props: WebPlayerOptions) {
    const status = ref('Connecting...');
    const error = ref<string | null>(null);
    const errorKind = ref<PlayerErrorKind>('generic');
    const track = ref<SpotifyTrack | null>(null);
    const paused = ref(true);
    const position = ref(0);
    const duration = ref(0);
    const autoplayBlocked = ref(false);
    const takenOver = ref(false);
    const active = ref(false);
    const deviceReady = ref(false);
    const tokenOk = ref(false);
    const lastUpdateAt = ref<number | null>(null);
    const positionStamp = ref(Date.now());

    const artists = computed(() => track.value?.artists.map((artist) => artist.name).join(', ') ?? '');
    const art = computed(() => track.value?.album?.images?.[0]?.url ?? null);
    const now = useTicker(
        () => track.value !== null && !paused.value,
        () => positionStamp.value,
    );
    const livePosition = computed(() => {
        if (paused.value || track.value === null) {
            return position.value;
        }

        return Math.min(duration.value, position.value + Math.max(0, now.value - positionStamp.value));
    });
    const seekPercent = computed(() =>
        duration.value > 0 ? Math.min(100, (livePosition.value / duration.value) * 100) : 0,
    );
    const showTransport = computed(() => !takenOver.value && (error.value === null || deviceReady.value));

    const instanceId = Math.random().toString(36).slice(2);
    let claimedAt = Date.now();
    let currentToken = '';
    let deviceId: string | null = null;
    let player: SpotifyPlayer | null = null;
    let reconnectAttempts = 0;
    let authFailures = 0;
    let reconnectTimer: ReturnType<typeof setTimeout> | null = null;

    const channel = useEcho().privateChannel(`webplayer.${props.partyCode}`) as WhisperChannel;

    channel.listen<{accessToken: string}>('.WebPlayerTokenRefreshed', (payload) => {
        currentToken = payload.accessToken;
    });

    function claim(): void {
        channel.whisper('claim', {instanceId, claimedAt});
    }

    function takeOver(): void {
        takenOver.value = true;
        active.value = false;
        deviceReady.value = false;
        status.value = 'Taken over elsewhere';
        if (reconnectTimer !== null) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        player?.disconnect();
    }

    channel.listenForWhisper<{instanceId: string; claimedAt: number}>('claim', (payload) => {
        if (takenOver.value || payload.instanceId === instanceId) {
            return;
        }
        const newer =
            payload.claimedAt > claimedAt || (payload.claimedAt === claimedAt && payload.instanceId > instanceId);
        if (newer) {
            takeOver();
        } else {
            claim();
        }
    });

    function command(action: (sdk: SpotifyPlayer) => Promise<void>): void {
        if (player !== null && !takenOver.value) {
            void action(player);
        }
    }

    channel.listen('.play', () => command((sdk) => sdk.resume()));
    channel.listen('.pause', () => command((sdk) => sdk.pause()));
    channel.listen('.next', () => command((sdk) => sdk.nextTrack()));
    channel.listen('.skip_prev', () => command((sdk) => sdk.previousTrack()));
    channel.listen<{position_ms: number}>('.seek', (payload) => command((sdk) => sdk.seek(payload.position_ms)));

    function useThisTab(): void {
        takenOver.value = false;
        status.value = 'Connecting...';
        claimedAt = Date.now();
        reconnectAttempts = 0;
        player = null;
        void fetchToken().then((ok) => (ok ? createPlayer() : failToken()));
    }

    function fail(message: string, kind: PlayerErrorKind = 'generic'): void {
        error.value = message;
        errorKind.value = kind;
        deviceReady.value = deviceReady.value && kind === 'generic';
        status.value = 'Unavailable';
        active.value = false;
    }

    let tokenRejected = false;

    function failToken(): void {
        if (tokenRejected) {
            fail('Spotify needs re-linking.', 'login');
        } else {
            fail('Could not fetch a Spotify access token.');
        }
    }

    async function fetchToken(): Promise<boolean> {
        try {
            const response = await axios.get<{accessToken: string}>(props.tokenUrl);
            currentToken = response.data.accessToken;
            tokenOk.value = true;

            return true;
        } catch (caught) {
            const code = (caught as {response?: {status?: number}}).response?.status;
            tokenRejected = code === 401 || code === 404 || code === 409;
            tokenOk.value = false;

            return false;
        }
    }

    async function reconnect(): Promise<void> {
        reconnectTimer = null;
        if (takenOver.value || player === null) {
            return;
        }
        if (!(await fetchToken())) {
            if (tokenRejected) {
                failToken();
            } else {
                scheduleReconnect();
            }

            return;
        }
        player.disconnect();
        void player.connect();
    }

    function scheduleReconnect(): void {
        if (takenOver.value || reconnectTimer !== null) {
            return;
        }
        const delay = Math.min(MAX_RECONNECT_DELAY_MS, 1000 * 2 ** reconnectAttempts);
        reconnectAttempts++;
        reconnectTimer = setTimeout(() => void reconnect(), delay);
    }

    function onStateChanged(state: SpotifyState | null): void {
        if (state === null || takenOver.value) {
            return;
        }
        const current = state.track_window.current_track;
        track.value = current;
        paused.value = state.paused;
        position.value = state.position;
        duration.value = state.duration;
        positionStamp.value = Date.now();
        lastUpdateAt.value = Date.now();
        if (!state.paused) {
            autoplayBlocked.value = false;
        }
        channel.whisper('state', {
            uri: current.uri,
            paused: state.paused,
            position: state.position,
            duration: state.duration,
            timestamp: state.timestamp,
            shuffle: state.shuffle,
            repeat: REPEAT_MODES[state.repeat_mode] ?? 'off',
            deviceId,
        });
    }

    function createPlayer(): void {
        if (takenOver.value) {
            return;
        }
        const spotify = (window as unknown as {Spotify: SpotifyGlobal}).Spotify;
        player = new spotify.Player({
            name: props.deviceName,
            getOAuthToken: (cb) => cb(currentToken),
            volume: 0.5,
        });
        player.addListener('ready', ({device_id}: {device_id: string}) => {
            deviceId = device_id;
            status.value = 'Ready';
            error.value = null;
            active.value = true;
            deviceReady.value = true;
            reconnectAttempts = 0;
            authFailures = 0;
            claim();
        });
        player.addListener('not_ready', () => {
            deviceReady.value = false;
            status.value = 'Device offline';
            scheduleReconnect();
        });
        player.addListener('authentication_error', () => {
            authFailures++;
            if (authFailures > 1) {
                fail('Spotify no longer accepts the saved login.', 'login');

                return;
            }
            status.value = 'Refreshing session...';
            scheduleReconnect();
        });
        player.addListener('autoplay_failed', () => {
            autoplayBlocked.value = true;
        });
        player.addListener('account_error', ({message}: {message: string}) => fail(message, 'premium'));
        player.addListener('initialization_error', ({message}: {message: string}) => fail(message));
        player.addListener('player_state_changed', onStateChanged);
        void player.connect();
    }

    function loadSdk(): void {
        (window as unknown as {onSpotifyWebPlaybackSDKReady?: () => void}).onSpotifyWebPlaybackSDKReady = createPlayer;
        if (document.querySelector(`script[src="${SDK_URL}"]`) !== null) {
            if ((window as unknown as {Spotify?: SpotifyGlobal}).Spotify) {
                createPlayer();
            }
            return;
        }
        const script = document.createElement('script');
        script.src = SDK_URL;
        script.async = true;
        document.body.appendChild(script);
    }

    async function startPlayback(): Promise<void> {
        await player?.activateElement();
        if (track.value !== null) {
            await player?.togglePlay();

            return;
        }
        if (deviceId === null) {
            return;
        }
        try {
            await axios.post(props.activateUrl, {device_id: deviceId});
        } catch (caught) {
            if ((caught as {response?: {status?: number}}).response?.status === 409) {
                fail('Spotify needs re-linking.', 'login');
            } else {
                fail('Could not start playback.');
            }
        }
    }

    async function resumePlayback(): Promise<void> {
        await player?.activateElement();
        await player?.resume();
        autoplayBlocked.value = false;
    }

    function seekTo(event: Event): void {
        command((sdk) => sdk.seek(Number((event.target as HTMLInputElement).value)));
    }

    function togglePlayback(): void {
        if (showTransport.value) {
            void startPlayback();
        }
    }

    useTransportShortcuts(
        {
            toggle: togglePlayback,
            next: () => command((sdk) => sdk.nextTrack()),
            previous: () => command((sdk) => sdk.previousTrack()),
        },
        () => !takenOver.value,
    );

    function warnBeforeUnload(event: BeforeUnloadEvent): void {
        if (active.value) {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    let removeNavigationGuard: (() => void) | null = null;

    onMounted(async () => {
        window.addEventListener('beforeunload', warnBeforeUnload);
        removeNavigationGuard = router.on('before', (event) => {
            if (active.value && !window.confirm(LEAVE_WARNING)) {
                event.preventDefault();
            }
        });
        if (!(await fetchToken())) {
            failToken();
            return;
        }
        loadSdk();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', warnBeforeUnload);
        removeNavigationGuard?.();
        if (reconnectTimer !== null) {
            clearTimeout(reconnectTimer);
        }
        player?.disconnect();
    });

    return {
        status,
        error,
        errorKind,
        track,
        paused,
        duration,
        autoplayBlocked,
        takenOver,
        active,
        deviceReady,
        tokenOk,
        lastUpdateAt,
        artists,
        art,
        livePosition,
        seekPercent,
        showTransport,
        command,
        useThisTab,
        startPlayback,
        resumePlayback,
        seekTo,
    };
}

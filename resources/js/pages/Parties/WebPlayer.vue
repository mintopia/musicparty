<script setup lang="ts">
import {router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import {useEcho} from '@/composables/useEcho';
import {formatClock, useTicker} from '@/composables/useProgress';
import {useTransportShortcuts} from '@/composables/useTransportShortcuts';
import BareLayout from '@/layouts/BareLayout.vue';
import {create as linkAccount} from '@/routes/linkedaccounts';

type SpotifyTrack = {
    name: string;
    uri: string;
    artists: Array<{name: string}>;
    album?: {name: string; images?: Array<{url: string}>};
};
type SpotifyState = {
    paused: boolean;
    position: number;
    duration: number;
    timestamp: number;
    shuffle: boolean;
    repeat_mode: 0 | 1 | 2;
    track_window: {current_track: SpotifyTrack};
};
type SpotifyPlayer = {
    addListener(event: string, cb: (payload: never) => void): boolean;
    connect(): Promise<boolean>;
    disconnect(): void;
    activateElement(): Promise<void>;
    togglePlay(): Promise<void>;
    resume(): Promise<void>;
    pause(): Promise<void>;
    nextTrack(): Promise<void>;
    previousTrack(): Promise<void>;
    seek(positionMs: number): Promise<void>;
};
type SpotifyGlobal = {
    Player: new (options: {
        name: string;
        getOAuthToken: (cb: (token: string) => void) => void;
        volume: number;
    }) => SpotifyPlayer;
};
type WhisperChannel = {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
    listenForWhisper<T>(event: string, cb: (payload: T) => void): unknown;
    whisper(event: string, data: Record<string, unknown>): unknown;
};

const SDK_URL = 'https://sdk.scdn.co/spotify-player.js';
const REPEAT_MODES = ['off', 'context', 'track'] as const;
const MAX_RECONNECT_DELAY_MS = 30000;
const SPOTIFY_ACCOUNT_URL = 'https://www.spotify.com/account/';
const PREMIUM_COPY =
    'The linked Spotify account is on the Free plan, and Spotify only lets Premium accounts play in a browser. Upgrade the account, or play on another device from the Dashboard.';
const LOGIN_COPY =
    "Spotify stopped accepting the saved login, so this tab can't play. Log in again and playback resumes here. Nobody else needs to do anything.";
const LEAVE_WARNING = 'This is the active Web Player for the Party. Leaving will stop playback.';

const props = defineProps<{
    party: {code: string; name: string};
    tokenUrl: string;
    activateUrl: string;
    deviceName: string;
}>();

const status = ref('Connecting...');
const error = ref<string | null>(null);
const errorKind = ref<'premium' | 'login' | 'generic'>('generic');
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
const updateTicker = useTicker(
    () => lastUpdateAt.value !== null,
    () => lastUpdateAt.value,
);
const lastUpdateLabel = computed(() => {
    if (lastUpdateAt.value === null) {
        return 'Never';
    }
    const seconds = Math.max(0, Math.floor((updateTicker.value - lastUpdateAt.value) / 1000));
    if (seconds < 60) {
        return `${seconds} ${seconds === 1 ? 'second' : 'seconds'} ago`;
    }
    const minutes = Math.floor(seconds / 60);

    return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
});
const reporting = computed(() => deviceReady.value && !takenOver.value && error.value === null);
const spotifyLoginUrl = linkAccount('spotify').url;
const errorTitle = computed(() =>
    errorKind.value === 'premium'
        ? 'Spotify Premium needed'
        : errorKind.value === 'login'
          ? 'Log in to Spotify again'
          : "This tab can't play",
);
const errorCopy = computed(() =>
    errorKind.value === 'premium' ? PREMIUM_COPY : errorKind.value === 'login' ? LOGIN_COPY : (error.value ?? ''),
);
const errorIcon = computed(() =>
    errorKind.value === 'login'
        ? 'ti-key-off'
        : errorKind.value === 'premium'
          ? 'ti-brand-spotify'
          : 'ti-alert-triangle',
);
type Check = {label: string; detail: string; tone: string; action?: {label: string; href: string; primary: boolean}};
const spotifyCheck = computed<Check>(() => {
    if (error.value !== null && errorKind.value === 'premium') {
        return {
            label: 'Spotify link',
            detail: 'Connected, but the account is Spotify Free',
            tone: 'red',
            action: {label: 'Open Spotify account', href: SPOTIFY_ACCOUNT_URL, primary: false},
        };
    }
    if (error.value !== null && errorKind.value === 'login') {
        return {
            label: 'Spotify link',
            detail: 'Spotify no longer accepts the saved login',
            tone: 'red',
            action: {label: 'Log in with Spotify', href: spotifyLoginUrl, primary: true},
        };
    }
    if (error.value !== null) {
        return {label: 'Spotify link', detail: error.value, tone: 'red'};
    }

    return tokenOk.value
        ? {label: 'Spotify link', detail: 'Connected', tone: 'green'}
        : {label: 'Spotify link', detail: 'Connecting...', tone: 'secondary'};
});
const deviceCheck = computed<Check>(() => {
    if (takenOver.value) {
        return {
            label: 'Device',
            detail: `${props.deviceName} (this tab) is no longer the active Web Player`,
            tone: 'yellow',
        };
    }

    return deviceReady.value
        ? {label: 'Device', detail: `${props.deviceName} (this tab)`, tone: 'green'}
        : {label: 'Device', detail: 'Not registered', tone: 'secondary'};
});
const reportingCheck = computed<Check>(() => {
    if (reporting.value) {
        return {label: 'Reporting', detail: 'Reporting to Music Party', tone: 'green'};
    }

    return takenOver.value
        ? {label: 'Reporting', detail: 'This tab stopped reporting', tone: 'secondary'}
        : {label: 'Reporting', detail: 'Not reporting to Music Party', tone: 'red'};
});
const updateCheck = computed<Check>(() => {
    if (lastUpdateAt.value === null) {
        return {label: 'Last update', detail: 'Never', tone: 'secondary'};
    }
    if (error.value !== null && errorKind.value === 'login') {
        return {label: 'Last update', detail: `${lastUpdateLabel.value}, before the login expired`, tone: 'yellow'};
    }

    return {label: 'Last update', detail: lastUpdateLabel.value, tone: 'green'};
});
const checks = computed<Check[]>(() => {
    if (takenOver.value) {
        return [
            deviceCheck.value,
            {label: 'Active Web Player', detail: 'Another Web Player has claimed this party', tone: 'green'},
            reportingCheck.value,
            spotifyCheck.value,
        ];
    }
    if (error.value !== null) {
        return [spotifyCheck.value, deviceCheck.value, reportingCheck.value, updateCheck.value];
    }

    return [deviceCheck.value, reportingCheck.value, updateCheck.value, spotifyCheck.value];
});
const pillLabel = computed(() => {
    if (takenOver.value) {
        return 'Taken over';
    }
    if (error.value !== null) {
        return 'Not connected';
    }
    if (track.value !== null && active.value) {
        return paused.value ? 'Paused' : 'Playing in this tab';
    }

    return status.value;
});
const pillWarn = computed(() => takenOver.value || error.value !== null || !active.value);
const showTransport = computed(() => !takenOver.value && (error.value === null || deviceReady.value));

const instanceId = Math.random().toString(36).slice(2);
let claimedAt = Date.now();
let currentToken = '';
let deviceId: string | null = null;
let player: SpotifyPlayer | null = null;
let reconnectAttempts = 0;
let authFailures = 0;
let reconnectTimer: ReturnType<typeof setTimeout> | null = null;

const channel = useEcho().privateChannel(`webplayer.${props.party.code}`) as WhisperChannel;

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
    const newer = payload.claimedAt > claimedAt || (payload.claimedAt === claimedAt && payload.instanceId > instanceId);
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

function fail(message: string, kind: 'premium' | 'login' | 'generic' = 'generic'): void {
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
</script>

<template>
    <BareLayout :title="`Web player - ${party.name}`">
        <div class="wp">
            <NowPlayingBackdrop :image="art" class="wp-hero">
                <div class="wp-wrap">
                    <div class="wp-top">
                        <h1><i class="ti ti-speakerphone" aria-hidden="true"></i>{{ party.name }} web player</h1>
                        <div role="status" aria-live="polite" data-test="status">
                            <PlayerStatusPill :warn="pillWarn">{{ pillLabel }}</PlayerStatusPill>
                        </div>
                    </div>
                    <div class="wp-main">
                        <img v-if="track && art && !takenOver && !error" class="wp-art" :src="art" alt="" />
                        <div v-else class="wp-art wp-art-blank">
                            <i
                                class="ti"
                                :class="takenOver ? 'ti-switch-horizontal' : error ? errorIcon : 'ti-music'"
                                aria-hidden="true"
                            ></i>
                        </div>
                        <div v-if="takenOver" data-test="taken-over">
                            <h2 class="wp-title">Another player took over</h2>
                            <p class="wp-copy">
                                Another Web Player is now playing for {{ party.name }}. This tab has stopped, so the
                                party isn't heard twice.
                            </p>
                            <div class="wp-action">
                                <button
                                    type="button"
                                    class="btn btn-light btn-lg"
                                    data-test="use-this-tab"
                                    @click="useThisTab"
                                >
                                    <i class="icon ti ti-player-play me-1"></i>Use this tab instead
                                </button>
                            </div>
                        </div>
                        <div v-else-if="error">
                            <h2 class="wp-title">{{ errorTitle }}</h2>
                            <p class="wp-copy" data-test="error">{{ errorCopy }}</p>
                            <div v-if="spotifyCheck.action" class="wp-action">
                                <a
                                    :href="spotifyCheck.action.href"
                                    class="btn btn-light btn-lg"
                                    data-test="error-action"
                                >
                                    <i
                                        class="icon ti me-1"
                                        :class="errorKind === 'login' ? 'ti-brand-spotify' : 'ti-external-link'"
                                    ></i
                                    >{{ spotifyCheck.action.label }}
                                </a>
                            </div>
                        </div>
                        <div v-else-if="track" data-test="now-playing">
                            <h2 class="wp-title" data-test="track-name">{{ track.name }}</h2>
                            <p class="wp-artists" data-test="track-artists">{{ artists }}</p>
                            <p v-if="track.album?.name" class="wp-album" data-test="track-album">
                                {{ track.album.name }}
                            </p>
                            <span class="visually-hidden" data-test="play-state">{{
                                paused ? 'Paused' : 'Playing'
                            }}</span>
                        </div>
                        <div v-else>
                            <h2 class="wp-title">{{ active ? 'Ready to play' : 'Connecting' }}</h2>
                            <p class="wp-copy">Press play to start the party's music in this tab.</p>
                        </div>
                    </div>
                    <template v-if="showTransport">
                        <div v-if="autoplayBlocked" class="wp-action text-center">
                            <button
                                type="button"
                                class="btn btn-warning btn-lg"
                                data-test="resume"
                                @click="resumePlayback"
                            >
                                Click to resume
                            </button>
                        </div>
                        <div class="wp-progress">
                            <span>{{ formatClock(livePosition) }}</span>
                            <input
                                type="range"
                                class="wp-seek"
                                :class="{'is-paused': paused}"
                                aria-label="Seek"
                                min="0"
                                :max="duration"
                                :value="livePosition"
                                :style="{'--wp-pct': `${seekPercent}%`}"
                                data-test="seek"
                                @change="seekTo"
                            />
                            <span>{{ formatClock(duration) }}</span>
                        </div>
                        <div class="wp-transport">
                            <button
                                type="button"
                                class="wp-btn"
                                aria-label="Previous track"
                                data-test="previous"
                                @click="command((sdk) => sdk.previousTrack())"
                            >
                                <i class="ti ti-player-skip-back-filled" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                class="wp-btn is-play"
                                :aria-label="paused ? 'Play' : 'Pause'"
                                data-test="start"
                                @click="startPlayback"
                            >
                                <i
                                    class="ti"
                                    :class="paused ? 'ti-player-play-filled' : 'ti-player-pause-filled'"
                                    aria-hidden="true"
                                ></i>
                            </button>
                            <button
                                type="button"
                                class="wp-btn"
                                aria-label="Next track"
                                data-test="next"
                                @click="command((sdk) => sdk.nextTrack())"
                            >
                                <i class="ti ti-player-skip-forward-filled" aria-hidden="true"></i>
                            </button>
                        </div>
                        <ul class="wp-hints" aria-label="Keyboard shortcuts">
                            <li><kbd>Space</kbd>Play or pause</li>
                            <li><kbd>N</kbd>Next</li>
                            <li><kbd>P</kbd>Previous</li>
                        </ul>
                    </template>
                </div>
            </NowPlayingBackdrop>
            <main class="wp-body">
                <div class="wp-wrap">
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">Player status</h2></div>
                        <ul class="mp-checks" data-test="player-checks">
                            <li v-for="check in checks" :key="check.label">
                                <span class="status-dot" :class="`status-${check.tone}`" aria-hidden="true"></span>
                                <span>
                                    <span class="mp-check-label">{{ check.label }}</span>
                                    <span class="mp-check-detail">{{ check.detail }}</span>
                                </span>
                                <a
                                    v-if="check.action"
                                    class="btn btn-sm"
                                    :class="check.action.primary ? 'btn-primary' : 'btn-outline-primary'"
                                    :href="check.action.href"
                                    >{{ check.action.label }}</a
                                >
                                <span v-else></span>
                            </li>
                        </ul>
                    </div>
                    <div v-if="!takenOver && deviceReady" class="card">
                        <div class="card-body wp-leave">
                            <i class="icon ti ti-volume fs-2 text-secondary" aria-hidden="true"></i>
                            <div>
                                <b>This tab is the party's speaker.</b>
                                <div class="text-secondary">
                                    Keep it open. Closing it or navigating away stops playback, and the browser asks you
                                    to confirm.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </BareLayout>
</template>

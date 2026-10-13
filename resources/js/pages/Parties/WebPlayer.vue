<script setup lang="ts">
import {router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import BareLayout from '@/layouts/BareLayout.vue';

type SpotifyTrack = {name: string; uri: string; artists: Array<{name: string}>};
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
const LEAVE_WARNING = 'This is the active Web Player for the Party. Leaving will stop playback.';

const props = defineProps<{
    party: {code: string; name: string};
    tokenUrl: string;
    deviceName: string;
}>();

const status = ref('Connecting...');
const error = ref<string | null>(null);
const track = ref<SpotifyTrack | null>(null);
const paused = ref(true);
const position = ref(0);
const duration = ref(0);
const autoplayBlocked = ref(false);
const takenOver = ref(false);
const active = ref(false);

const artists = computed(() => track.value?.artists.map((artist) => artist.name).join(', ') ?? '');

const instanceId = Math.random().toString(36).slice(2);
const claimedAt = Date.now();
let currentToken = '';
let deviceId: string | null = null;
let player: SpotifyPlayer | null = null;
let reconnectAttempts = 0;
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

function fail(message: string): void {
    error.value = message;
    status.value = 'Unavailable';
    active.value = false;
}

async function fetchToken(): Promise<boolean> {
    try {
        const response = await axios.get<{accessToken: string}>(props.tokenUrl);
        currentToken = response.data.accessToken;

        return true;
    } catch {
        return false;
    }
}

async function reconnect(): Promise<void> {
    reconnectTimer = null;
    if (takenOver.value || player === null) {
        return;
    }
    if (!(await fetchToken())) {
        scheduleReconnect();

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
        reconnectAttempts = 0;
        claim();
    });
    player.addListener('not_ready', () => {
        status.value = 'Device offline';
        scheduleReconnect();
    });
    player.addListener('authentication_error', () => {
        status.value = 'Refreshing session...';
        scheduleReconnect();
    });
    player.addListener('autoplay_failed', () => {
        autoplayBlocked.value = true;
    });
    for (const event of ['initialization_error', 'account_error']) {
        player.addListener(event, ({message}: {message: string}) => fail(message));
    }
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
    await player?.togglePlay();
}

async function resumePlayback(): Promise<void> {
    await player?.activateElement();
    await player?.resume();
    autoplayBlocked.value = false;
}

function seekTo(event: Event): void {
    command((sdk) => sdk.seek(Number((event.target as HTMLInputElement).value)));
}

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
        fail('Could not fetch a Spotify access token.');
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
        <div class="container py-4">
            <div class="col-lg-6 offset-lg-3">
                <div class="page-header mt-0">
                    <h1>{{ party.name }} web player</h1>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p class="mb-2" data-test="status">{{ status }}</p>
                        <div v-if="error" class="alert alert-danger" role="alert" data-test="error">{{ error }}</div>
                        <div v-if="track" data-test="now-playing">
                            <div class="fw-bold" data-test="track-name">{{ track.name }}</div>
                            <div class="text-secondary" data-test="track-artists">{{ artists }}</div>
                            <div class="mt-1" data-test="play-state">{{ paused ? 'Paused' : 'Playing' }}</div>
                        </div>
                        <div v-if="takenOver" class="alert alert-warning mt-3" role="alert" data-test="taken-over">
                            Taken over elsewhere. Another Web Player is now active for this Party.
                        </div>
                        <template v-else>
                            <button
                                v-if="autoplayBlocked"
                                type="button"
                                class="btn btn-warning mt-3"
                                data-test="resume"
                                @click="resumePlayback"
                            >
                                Click to resume
                            </button>
                            <div class="mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-test="previous" @click="command((sdk) => sdk.previousTrack())">
                                    Previous
                                </button>
                                <button type="button" class="btn btn-primary" data-test="start" @click="startPlayback">
                                    {{ paused ? 'Play' : 'Pause' }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-test="next" @click="command((sdk) => sdk.nextTrack())">
                                    Next
                                </button>
                            </div>
                            <input
                                type="range"
                                class="form-range mt-3"
                                min="0"
                                :max="duration"
                                :value="position"
                                data-test="seek"
                                @change="seekTo"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </BareLayout>
</template>

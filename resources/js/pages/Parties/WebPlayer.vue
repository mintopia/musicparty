<script setup lang="ts">
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
    whisper(event: string, data: Record<string, unknown>): unknown;
};

const SDK_URL = 'https://sdk.scdn.co/spotify-player.js';
const REPEAT_MODES = ['off', 'context', 'track'] as const;

const props = defineProps<{
    party: {code: string; name: string};
    tokenUrl: string;
    deviceName: string;
}>();

const status = ref('Connecting...');
const error = ref<string | null>(null);
const track = ref<SpotifyTrack | null>(null);
const paused = ref(true);

const artists = computed(() => track.value?.artists.map((artist) => artist.name).join(', ') ?? '');

let currentToken = '';
let deviceId: string | null = null;
let player: SpotifyPlayer | null = null;

const channel = useEcho().privateChannel(`webplayer.${props.party.code}`) as WhisperChannel;

channel.listen<{accessToken: string}>('.WebPlayerTokenRefreshed', (payload) => {
    currentToken = payload.accessToken;
});

function fail(message: string): void {
    error.value = message;
    status.value = 'Unavailable';
}

function onStateChanged(state: SpotifyState | null): void {
    if (state === null) {
        return;
    }
    const current = state.track_window.current_track;
    track.value = current;
    paused.value = state.paused;
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
    });
    player.addListener('not_ready', () => {
        status.value = 'Device offline';
    });
    for (const event of ['initialization_error', 'authentication_error', 'account_error']) {
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

onMounted(async () => {
    try {
        const response = await axios.get<{accessToken: string}>(props.tokenUrl);
        currentToken = response.data.accessToken;
    } catch {
        fail('Could not fetch a Spotify access token.');
        return;
    }
    loadSdk();
});

onBeforeUnmount(() => {
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
                        <button type="button" class="btn btn-primary mt-3" data-test="start" @click="startPlayback">
                            Click to start playback
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </BareLayout>
</template>

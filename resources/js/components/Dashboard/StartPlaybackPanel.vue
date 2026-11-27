<script setup lang="ts">
import {computed, inject, onBeforeUnmount, ref, watch} from 'vue';
import DevicePicker from '@/components/Dashboard/DevicePicker.vue';
import {
    badgeFor,
    errorCode,
    fetchDevices,
    formatMs,
    notifyKey,
    reloadDashboard,
    startLabel,
    startPlayback,
    type PanelState,
} from '@/lib/dashboard';
import type {DashboardControl} from '@/types';

const props = defineProps<{
    code: string;
    host: string;
    start: DashboardControl['start'];
    playingFromLabel: string | null;
    canControl: boolean;
}>();

const notify = inject(notifyKey, () => undefined);

const starting = ref(false);
const refreshing = ref(false);
const localState = ref<PanelState | null>(null);
const inlineError = ref<string | null>(null);
const devices = ref(props.start.devices);
const selected = ref<string | null>(props.start.selectedDeviceId);
const retryIn = ref(0);
let countdown: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.start,
    (next) => {
        devices.value = next.devices;
        selected.value = next.selectedDeviceId;
        localState.value = null;
        inlineError.value = null;
    },
);

const state = computed<PanelState>(() => {
    if (starting.value) {
        return 'starting';
    }
    if (retryIn.value > 0) {
        return 'rate_limited';
    }
    return localState.value ?? props.start.state;
});
const badge = computed(() => badgeFor(state.value));
const target = computed(() => props.start.target);
const selectedDevice = computed(() => devices.value.find((device) => device.id === selected.value) ?? null);
const showDevices = computed(() => !['premium_required', 'relink_required'].includes(state.value));
const showWill = computed(() => ['outside', 'idle'].includes(state.value) && target.value !== null);
const showButton = computed(() => state.value !== 'relink_required');
const disabled = computed(
    () =>
        !props.canControl ||
        starting.value ||
        retryIn.value > 0 ||
        ['no_device', 'premium_required'].includes(state.value) ||
        selectedDevice.value === null ||
        selectedDevice.value.isRestricted ||
        target.value === null,
);
const label = computed(() => startLabel(state.value, props.host));
const targetArtist = computed(() => (target.value?.track.artists ?? []).map((a) => a.name).join(', '));

function stopCountdown(): void {
    if (countdown !== null) {
        clearInterval(countdown);
        countdown = null;
    }
}

function beginCountdown(seconds: number): void {
    stopCountdown();
    retryIn.value = Math.max(1, Math.ceil(seconds));
    countdown = setInterval(() => {
        retryIn.value -= 1;
        if (retryIn.value <= 0) {
            retryIn.value = 0;
            stopCountdown();
        }
    }, 1000);
}

async function refreshDevices(): Promise<void> {
    refreshing.value = true;
    try {
        devices.value = await fetchDevices(props.code);
        if (!devices.value.some((device) => device.id === selected.value)) {
            selected.value = devices.value.find((device) => device.isActive && !device.isRestricted)?.id ?? null;
        }
        if (devices.value.length > 0 && localState.value === 'no_device') {
            localState.value = null;
        }
    } catch (error) {
        const failure = errorCode(error);
        if (failure.code === 'rate_limited') {
            beginCountdown(failure.retryAfter ?? 5);
        } else {
            notify(failure.message);
        }
    } finally {
        refreshing.value = false;
    }
}

async function begin(): Promise<void> {
    inlineError.value = null;
    starting.value = true;
    try {
        await startPlayback(props.code, selected.value);
        reloadDashboard();
    } catch (error) {
        const failure = errorCode(error);
        switch (failure.code) {
            case 'premium_required':
            case 'no_device':
            case 'relink_required':
                localState.value = failure.code;
                break;
            case 'rate_limited':
                beginCountdown(failure.retryAfter ?? 5);
                break;
            case 'device_restricted':
                inlineError.value = failure.message;
                break;
            case 'device_not_found':
                inlineError.value = failure.message;
                void refreshDevices();
                break;
            default:
                notify(failure.message);
        }
    } finally {
        starting.value = false;
    }
}

onBeforeUnmount(stopCountdown);
</script>

<template>
    <div class="deck-panel" data-bs-theme="dark" data-test="start-panel">
        <div class="d-flex align-items-center mb-2">
            <h3 class="mb-0 flex-grow-1 text-white">Start Playback</h3>
            <span class="badge" :class="badge.cls" data-test="start-badge">{{ badge.text }}</span>
        </div>

        <div class="small" data-test="start-message">
            <template v-if="state === 'outside'"
                >Spotify is playing <strong>{{ playingFromLabel ?? 'something else' }}</strong
                >. Requests won't play until you start the party.</template
            >
            <template v-else-if="state === 'resume' && target"
                >Nothing is playing on {{ host }}'s Spotify. The party stopped part-way through
                <strong>{{ target.track.name }}</strong> at {{ formatMs(target.positionMs) }}.</template
            >
            <template v-else-if="state === 'starting'"
                >Asking Spotify to start the party on {{ selectedDevice?.name ?? 'your device' }}.</template
            >
            <template v-else-if="state === 'rate_limited'">Spotify is busy, try again in {{ retryIn }} s.</template>
            <template v-else-if="state === 'no_device'"
                >No Spotify app is open on {{ host }}'s account. Open Spotify on the DJ laptop or wake the Encore
                speaker, then refresh.</template
            >
            <template v-else-if="state === 'premium_required'"
                >Spotify only allows remote control on Premium accounts, and {{ host }}'s account is on Spotify Free.
                Ask {{ host }} to upgrade, then try again.</template
            >
            <template v-else-if="state === 'relink_required'"
                >{{ host }}'s Spotify link has expired, so Music Party can't control playback. Ask {{ host }} to sign in
                and re-link Spotify.</template
            >
            <template v-else>Nothing is playing on {{ host }}'s Spotify.</template>
        </div>

        <template v-if="showDevices">
            <div class="form-label text-white-50 mt-3 mb-2">Play on</div>
            <DevicePicker
                v-model="selected"
                :devices="devices"
                :host="host"
                :locked="starting"
                :refreshing="refreshing"
                @refresh="refreshDevices"
            />
        </template>

        <template v-if="showWill && target">
            <div class="form-label text-white-50 mt-3 mb-2">Will play</div>
            <div class="d-flex align-items-center gap-2" data-test="will-play">
                <img class="will-art" :src="target.track.album?.image_url" alt="" />
                <div class="lh-sm">
                    <div class="fw-medium">{{ target.track.name }}</div>
                    <div class="text-secondary small">{{ targetArtist }} · Up Next</div>
                </div>
            </div>
        </template>

        <div v-if="inlineError" class="deck-error small mt-3" role="alert" data-test="start-error">
            {{ inlineError }}
        </div>

        <template v-if="showButton">
            <div class="mt-3">
                <button
                    type="button"
                    class="btn btn-primary btn-start w-100"
                    :disabled="disabled"
                    data-test="start-button"
                    @click="begin"
                >
                    <span v-if="starting" class="spinner-border me-2" role="status" data-test="start-spinner"></span>
                    <i v-else-if="state === 'resume'" class="ti ti-player-play me-2"></i>
                    <i v-else class="ti ti-brand-spotify me-2"></i>
                    {{ label }}
                </button>
            </div>
            <div class="text-secondary small mt-2">Runs on {{ host }}'s Spotify account, not yours.</div>
        </template>
    </div>
</template>

<style scoped>
.deck-panel {
    background: var(--mp-deck-surface);
    border: 1px solid var(--mp-deck-border);
    border-radius: 12px;
    padding: 16px 18px;
    color: var(--mp-deck-text);
    --tblr-secondary: var(--mp-deck-muted);
    --tblr-muted: var(--mp-deck-muted);
    height: 100%;
}
.btn-start {
    min-height: 52px;
    font-size: 1rem;
    font-weight: 600;
}
.btn-start .spinner-border {
    width: 1.1rem;
    height: 1.1rem;
}
.will-art {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    object-fit: cover;
}
.deck-error {
    color: var(--mp-deck-accent-danger);
}
</style>

<script setup>
import {Link, router} from '@inertiajs/vue3';
import {onMounted, ref, watch} from 'vue';

const props = defineProps({
    playerKind: {type: String, default: null},
    playerKinds: {type: Array, default: () => []},
    playbackDevice: {type: Object, default: null},
    playerTokensUrl: {type: String, default: null},
    devicesUrl: {type: String, default: null},
    playerUpdateUrl: {type: String, default: null},
    spotifyLinkUrl: {type: String, default: null},
});

const kind = ref(props.playerKind ?? '');
const deviceId = ref(props.playbackDevice?.id ?? '');
const devices = ref([]);
const state = ref('idle');

const loadDevices = async () => {
    state.value = 'loading';
    try {
        const response = await fetch(props.devicesUrl, {headers: {Accept: 'application/json'}});
        if (response.status === 409) {
            state.value = 'relink';
            return;
        }
        if (!response.ok) {
            state.value = 'error';
            return;
        }
        devices.value = (await response.json()).data ?? [];
        state.value = devices.value.length ? 'list' : 'empty';
    } catch {
        state.value = 'error';
    }
};

const changeKind = () => {
    router.put(props.playerUpdateUrl, {player_kind: kind.value}, {preserveScroll: true});
};

const changeDevice = () => {
    const device = devices.value.find((candidate) => candidate.id === deviceId.value);
    router.put(props.playerUpdateUrl, {
        player_kind: kind.value,
        device_id: device?.id ?? null,
        device_name: device?.name ?? null,
    }, {preserveScroll: true});
};

watch(kind, (value) => {
    if (value === 'polling' && state.value === 'idle') {
        loadDevices();
    }
});

onMounted(() => {
    if (kind.value === 'polling') {
        loadDevices();
    }
});
</script>

<template>
    <section class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5" data-testid="player-section">
        <h2 class="text-base font-bold">Player</h2>

        <div class="flex flex-col gap-1 text-sm">
            <label for="player-kind" class="font-medium">Player</label>
            <select
                id="player-kind"
                v-model="kind"
                data-testid="player-kind"
                class="min-h-11 rounded border border-border bg-surface px-2"
                @change="changeKind"
            >
                <option v-for="option in playerKinds" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </div>

        <div v-if="kind === 'polling'" class="flex flex-col gap-1 text-sm" data-testid="device-picker">
            <span class="font-medium">Playback device</span>
            <p v-if="state === 'loading'" data-testid="devices-loading" class="text-muted">Loading devices...</p>
            <template v-else-if="state === 'list'">
                <select
                    v-model="deviceId"
                    data-testid="device-select"
                    class="min-h-11 rounded border border-border bg-surface px-2"
                    @change="changeDevice"
                >
                    <option value="">Any active device</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">{{ device.name }}</option>
                </select>
            </template>
            <p v-else-if="state === 'empty'" data-testid="devices-empty" class="text-muted">No Spotify devices found. Open Spotify on a device and try again.</p>
            <div v-else-if="state === 'relink'" data-testid="devices-relink" class="flex flex-col gap-2">
                <p class="text-red-500">Your Spotify link needs to be renewed before devices can be listed.</p>
                <div v-if="spotifyLinkUrl">
                    <Link
                        :href="spotifyLinkUrl"
                        data-testid="relink-link"
                        class="inline-flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
                    >Re-link Spotify account</Link>
                </div>
            </div>
            <p v-else-if="state === 'error'" data-testid="devices-error" role="alert" class="text-red-500">Could not load devices.</p>
            <div v-if="state !== 'loading' && state !== 'idle'">
                <button
                    type="button"
                    data-testid="devices-refresh"
                    class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary"
                    @click="loadDevices"
                >Refresh devices</button>
            </div>
        </div>

        <div v-else-if="kind === 'soloist'" data-testid="soloist-help">
            <Link
                :href="playerTokensUrl"
                data-testid="player-tokens-link"
                class="inline-flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Manage Player Tokens</Link>
        </div>
    </section>
</template>

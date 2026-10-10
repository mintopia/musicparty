<script setup>
import {router} from '@inertiajs/vue3';
import {ref} from 'vue';

const props = defineProps({
    partyCode: {type: String, required: true},
    supported: {type: Boolean, default: true},
});

const pending = ref(false);
const error = ref(null);
const volume = ref(50);
const seekSeconds = ref(0);

const send = (control, payload = {}) => {
    if (pending.value) {
        return;
    }
    router.post(`/parties/${props.partyCode}/playback/${control}`, payload, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = true;
            error.value = null;
        },
        onError: (e) => {
            error.value = e.playback ?? e.position_ms ?? e.level ?? 'Could not control playback.';
        },
        onFinish: () => {
            pending.value = false;
        },
    });
};

const seek = () => send('seek', {position_ms: Math.round(Number(seekSeconds.value) * 1000)});
const setVolume = () => send('volume', {level: Number(volume.value)});

const buttonClass = 'min-h-11 rounded border border-border px-4 text-sm disabled:opacity-50';
</script>

<template>
    <div class="flex flex-col gap-2" data-testid="playback-controls">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-testid="playback-play" :disabled="pending || !supported" :class="buttonClass" @click="send('play')">Play</button>
            <button type="button" data-testid="playback-pause" :disabled="pending || !supported" :class="buttonClass" @click="send('pause')">Pause</button>
            <button type="button" data-testid="playback-skip" :disabled="pending || !supported" :class="buttonClass" @click="send('skip')">Skip</button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm">
                Seek (s)
                <input v-model="seekSeconds" data-testid="playback-seek-input" type="number" min="0" class="min-h-11 w-24 rounded border border-border bg-transparent px-2">
            </label>
            <button type="button" data-testid="playback-seek" :disabled="pending || !supported" :class="buttonClass" @click="seek">Seek</button>
        </div>
        <label class="flex items-center gap-2 text-sm">
            Volume
            <input v-model="volume" data-testid="playback-volume-input" type="range" min="0" max="100" :disabled="pending || !supported" @change="setVolume">
        </label>
        <p v-if="!supported" data-testid="playback-unsupported" class="text-sm text-muted">This Player does not support playback controls.</p>
        <p v-if="error" role="alert" data-testid="playback-error" class="text-sm text-danger">{{ error }}</p>
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue';

export type PlaybackState = 'playing' | 'paused' | 'stale' | 'idle';

const props = defineProps<{state: PlaybackState}>();

const labels: Record<PlaybackState, string> = {
    playing: 'Playing',
    paused: 'Paused',
    stale: 'Player stale',
    idle: 'Nothing playing',
};

const modifier = computed(() => `is-${props.state}`);
</script>

<template>
    <span class="mp-state" :class="modifier" data-test="state-pill">
        <span v-if="state === 'playing'" class="mp-eq" aria-hidden="true"><i></i><i></i><i></i></span>
        <slot>{{ labels[state] }}</slot>
    </span>
</template>

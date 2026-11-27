<script setup lang="ts">
import {computed} from 'vue';

const props = withDefaults(defineProps<{percent: number; label?: string}>(), {label: 'Playback progress'});

const clamped = computed(() => Math.min(100, Math.max(0, Number.isFinite(props.percent) ? props.percent : 0)));
</script>

<template>
    <div
        class="playback-progress"
        role="progressbar"
        :aria-label="label"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="Math.round(clamped)"
    >
        <div class="playback-progress-fill" :style="{transform: `scaleX(${clamped / 100})`}"></div>
    </div>
</template>

<style scoped>
.playback-progress {
    height: var(--playback-progress-height, 5px);
    border-radius: 999px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.2);
}

.playback-progress-fill {
    height: 100%;
    background: var(--tblr-primary);
    transform-origin: left center;
    transition: transform 1s linear;
    will-change: transform;
}

@media (prefers-reduced-motion: reduce) {
    .playback-progress-fill {
        transition: none;
    }
}
</style>

<script setup lang="ts">
import {formatClock} from '@/composables/useProgress';

defineProps<{
    autoplayBlocked: boolean;
    paused: boolean;
    duration: number;
    livePosition: number;
    seekPercent: number;
}>();

defineEmits<{
    resume: [];
    seek: [event: Event];
    previous: [];
    toggle: [];
    next: [];
}>();
</script>

<template>
    <div v-if="autoplayBlocked" class="wp-action text-center">
        <button type="button" class="btn btn-warning btn-lg" data-test="resume" @click="$emit('resume')">
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
            @change="$emit('seek', $event)"
        />
        <span>{{ formatClock(duration) }}</span>
    </div>
    <div class="wp-transport">
        <button
            type="button"
            class="wp-btn"
            aria-label="Previous track"
            data-test="previous"
            @click="$emit('previous')"
        >
            <i class="ti ti-player-skip-back-filled" aria-hidden="true"></i>
        </button>
        <button
            type="button"
            class="wp-btn is-play"
            :aria-label="paused ? 'Play' : 'Pause'"
            data-test="start"
            @click="$emit('toggle')"
        >
            <i class="ti" :class="paused ? 'ti-player-play-filled' : 'ti-player-pause-filled'" aria-hidden="true"></i>
        </button>
        <button type="button" class="wp-btn" aria-label="Next track" data-test="next" @click="$emit('next')">
            <i class="ti ti-player-skip-forward-filled" aria-hidden="true"></i>
        </button>
    </div>
    <ul class="wp-hints" aria-label="Keyboard shortcuts">
        <li><kbd>Space</kbd>Play or pause</li>
        <li><kbd>N</kbd>Next</li>
        <li><kbd>P</kbd>Previous</li>
    </ul>
</template>

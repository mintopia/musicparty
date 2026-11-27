<script setup lang="ts">
import {computed, ref} from 'vue';
import {formatMs} from '@/lib/dashboard';

const props = defineProps<{positionMs: number; durationMs: number; disabled?: boolean}>();
const emit = defineEmits<{seek: [positionMs: number]}>();

const track = ref<HTMLElement | null>(null);
const dragging = ref<number | null>(null);

const shown = computed(() => dragging.value ?? props.positionMs);
const percent = computed(() => (props.durationMs > 0 ? Math.min(100, (shown.value / props.durationMs) * 100) : 0));

function positionFrom(event: PointerEvent): number {
    const rect = track.value?.getBoundingClientRect();
    if (!rect || rect.width === 0) {
        return 0;
    }
    return Math.round(Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)) * props.durationMs);
}

function down(event: PointerEvent): void {
    if (props.disabled || props.durationMs <= 0) {
        return;
    }
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
    dragging.value = positionFrom(event);
}

function move(event: PointerEvent): void {
    if (dragging.value !== null) {
        dragging.value = positionFrom(event);
    }
}

function up(): void {
    if (dragging.value !== null) {
        emit('seek', dragging.value);
        dragging.value = null;
    }
}

function key(event: KeyboardEvent): void {
    if (props.disabled || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
        return;
    }
    event.preventDefault();
    const delta = event.key === 'ArrowRight' ? 5000 : -5000;
    emit('seek', Math.min(props.durationMs, Math.max(0, props.positionMs + delta)));
}
</script>

<template>
    <div>
        <div
            ref="track"
            class="seek"
            role="slider"
            tabindex="0"
            aria-label="Seek"
            :aria-valuenow="Math.floor(shown / 1000)"
            aria-valuemin="0"
            :aria-valuemax="Math.floor(durationMs / 1000)"
            :aria-valuetext="formatMs(shown)"
            :aria-disabled="disabled"
            data-test="seek"
            @pointerdown="down"
            @pointermove="move"
            @pointerup="up"
            @pointercancel="dragging = null"
            @keydown="key"
        >
            <div class="seek-fill" :style="{width: `${percent}%`}"></div>
            <div class="seek-knob" :style="{left: `${percent}%`}"></div>
        </div>
        <div class="pu-times">
            <span>{{ formatMs(shown) }}</span
            ><span>{{ formatMs(durationMs) }}</span>
        </div>
    </div>
</template>

<style scoped>
.seek {
    position: relative;
    height: 6px;
    border-radius: 3px;
    background: var(--mp-deck-raised-hover);
    cursor: pointer;
    margin: 14px 0 8px;
    touch-action: none;
}
.seek::before {
    content: '';
    position: absolute;
    inset: -10px 0;
}
.seek:focus-visible {
    outline: 2px solid var(--mp-deck-focus);
    outline-offset: 6px;
}
.seek-fill {
    position: absolute;
    inset: 0 auto 0 0;
    background: var(--tblr-primary);
    border-radius: 3px;
}
.seek-knob {
    position: absolute;
    top: 50%;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--mp-deck-text);
    transform: translate(-50%, -50%);
    box-shadow: 0 1px 3px var(--mp-deck-shadow);
}
.pu-times {
    display: flex;
    justify-content: space-between;
    font-variant-numeric: tabular-nums;
    font-size: 0.875rem;
    color: var(--mp-deck-muted);
}
</style>

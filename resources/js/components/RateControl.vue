<script setup lang="ts">
import type {RatingValue} from '@/composables/useRating';

const props = withDefaults(defineProps<{rating: number | null | undefined; pressed?: RatingValue}>(), {pressed: 0});
const emit = defineEmits<{rate: [value: RatingValue]}>();

function choose(direction: 1 | -1): void {
    emit('rate', props.pressed === direction ? 0 : direction);
}
</script>

<template>
    <div class="rate-control" data-test="rate-control">
        <button
            type="button"
            class="rate-button"
            aria-label="Rate up"
            :aria-pressed="pressed === 1"
            data-test="rate-up"
            @click="choose(1)"
        >
            <i class="icon ti ti-thumb-up" aria-hidden="true"></i>
        </button>
        <span class="rate-count" data-test="rating">{{ rating ?? 0 }}</span>
        <button
            type="button"
            class="rate-button"
            aria-label="Rate down"
            :aria-pressed="pressed === -1"
            data-test="rate-down"
            @click="choose(-1)"
        >
            <i class="icon ti ti-thumb-down" aria-hidden="true"></i>
        </button>
    </div>
</template>

<style scoped>
.rate-control {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    border-radius: 999px;
    background: var(--rate-bg, rgba(255, 255, 255, 0.1));
    color: var(--rate-fg, #fff);
    flex: none;
}

.rate-button {
    appearance: none;
    position: relative;
    display: grid;
    place-items: center;
    inline-size: 32px;
    block-size: 32px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: inherit;
    cursor: pointer;
    transition: background-color 0.15s ease-out;
}

.rate-button::after {
    content: '';
    position: absolute;
    inset: -6px;
}

.rate-button:hover {
    background: rgba(255, 255, 255, 0.14);
}

.rate-button:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}

.rate-button[aria-pressed='true'] {
    background: var(--rate-pressed-bg, #fff);
    color: var(--rate-pressed-fg, #111);
}

.rate-button .icon {
    font-size: 18px;
}

.rate-count {
    min-inline-size: 1.6em;
    padding-inline-end: 4px;
    border-inline-end: 1px solid var(--rate-divider, rgba(255, 255, 255, 0.2));
    text-align: center;
    font-variant-numeric: tabular-nums;
    font-size: 14px;
    font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
    .rate-button {
        transition: none;
    }
}
</style>

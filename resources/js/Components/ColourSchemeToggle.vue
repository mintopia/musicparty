<script setup>
import {SCHEMES, useColourScheme} from '../colourScheme';

const {scheme, setScheme} = useColourScheme();

const labels = {light: 'Light', dark: 'Dark', system: 'System'};
</script>

<template>
    <div
        data-testid="colour-scheme-toggle"
        role="group"
        aria-label="Colour scheme"
        class="inline-flex overflow-hidden rounded border border-border bg-surface text-xs"
    >
        <button
            v-for="option in SCHEMES"
            :key="option"
            type="button"
            :data-scheme="option"
            :aria-pressed="scheme === option"
            class="px-2 py-1 text-text"
            :class="scheme === option ? 'bg-border font-semibold' : ''"
            @click="setScheme(option)"
        >
            {{ labels[option] }}
        </button>
    </div>
</template>

<script setup>
import {iconPaths} from '../lib/icons';

defineProps({name: {type: String, required: true}, filled: {type: Boolean, default: false}});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :fill="filled ? 'currentColor' : 'none'"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="h-5 w-5 shrink-0"
    >
        <path v-for="d in iconPaths[name]" :key="d" :d="d" />
    </svg>
</template>

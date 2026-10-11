<script setup>
import {computed} from 'vue';
import {absoluteTime, relativeTime} from '../../lib/format.js';

const props = defineProps({value: {type: String, default: null}, empty: {type: String, default: 'Never'}});
const text = computed(() => relativeTime(props.value));
</script>

<template>
    <time v-if="text" :datetime="value" :title="absoluteTime(value)">{{ text }}</time>
    <span v-else class="text-muted">{{ empty }}</span>
</template>

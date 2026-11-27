<script setup lang="ts">
import {computed} from 'vue';

const props = defineProps<{image?: string | null}>();

const style = computed(() =>
    props.image ? {'--np-image': `url("${props.image.replace(/["\\]/g, '\\$&')}")`} : undefined,
);
</script>

<template>
    <section class="mp-stage" :style="style" data-test="stage-surface"><slot /></section>
</template>

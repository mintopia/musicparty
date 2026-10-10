<script setup>
defineProps({href: {type: String, default: null}});
</script>

<template>
    <a v-if="href" :href="href" target="_blank" rel="noopener" class="contents hover:underline"><slot /></a>
    <slot v-else />
</template>

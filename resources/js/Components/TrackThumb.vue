<script setup>
defineProps({src: {type: String, default: null}});
</script>

<template>
    <img v-if="src" :src="src" alt="" class="h-12 w-12 shrink-0 rounded object-cover md:h-14 md:w-14" />
    <div v-else class="h-12 w-12 shrink-0 rounded bg-border md:h-14 md:w-14" aria-hidden="true"></div>
</template>

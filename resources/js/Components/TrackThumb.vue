<script setup>
defineProps({src: {type: String, default: null}});
</script>

<template>
    <img v-if="src" :src="src" alt="" class="h-[60px] w-[60px] shrink-0 rounded object-cover" />
    <div v-else data-testid="cover-placeholder" class="h-[60px] w-[60px] shrink-0 rounded bg-gradient-to-br from-border to-muted/40" aria-hidden="true"></div>
</template>

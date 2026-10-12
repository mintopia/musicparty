<script setup>
defineProps({src: {type: String, default: null}});
</script>

<template>
    <img v-if="src" :src="src" alt="" class="h-[60px] w-[60px] shrink-0 rounded object-cover" />
    <div v-else data-testid="cover-placeholder" class="flex h-[60px] w-[60px] shrink-0 items-center justify-center rounded bg-gradient-to-br from-blue-500 to-indigo-800" aria-hidden="true">
        <svg viewBox="0 0 24 24" class="h-7 w-7 text-white/50" fill="currentColor"><path d="M12 3l9 17H3z" /></svg>
    </div>
</template>

<script setup lang="ts">
withDefaults(
    defineProps<{
        src: string | null;
        alt?: string;
        size?: number;
        round?: boolean;
        icon?: string;
    }>(),
    {alt: '', round: false, icon: 'ti-disc'},
);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="alt"
        class="search-cover"
        :class="round ? 'rounded-circle' : 'rounded'"
        :style="size ? {width: `${size}px`, height: `${size}px`} : undefined"
        loading="lazy"
        data-test="cover"
    />
    <span
        v-else
        class="search-cover bg-secondary-lt text-secondary d-flex align-items-center justify-content-center"
        :class="round ? 'rounded-circle' : 'rounded'"
        :style="size ? {width: `${size}px`, height: `${size}px`} : undefined"
        aria-hidden="true"
        data-test="cover-fallback"
    >
        <i class="icon ti" :class="icon"></i>
    </span>
</template>

<style scoped>
.search-cover {
    display: block;
    flex: 0 0 auto;
    object-fit: cover;
}

.search-cover:not([style]) {
    width: 100%;
    aspect-ratio: 1;
}
</style>

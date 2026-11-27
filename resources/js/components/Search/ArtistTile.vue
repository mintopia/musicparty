<script setup lang="ts">
import SearchCover from '@/components/Search/SearchCover.vue';
import type {ArtistResult} from '@/types';

withDefaults(defineProps<{artist: ArtistResult; href: string; subtitle?: string}>(), {subtitle: 'Artist'});
</script>

<template>
    <a :href="href" class="search-tile text-reset text-decoration-none text-center" data-test="artist-tile">
        <SearchCover :src="artist.image_url" :alt="artist.name" round icon="ti-user" />
        <div class="fw-medium lh-sm mt-2 clamp-2" :title="artist.name" data-test="artist-name">{{ artist.name }}</div>
        <div class="text-secondary small" data-test="artist-subtitle">{{ subtitle }}</div>
    </a>
</template>

<style scoped>
.search-tile {
    min-width: 0;
}

.clamp-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    overflow-wrap: anywhere;
}
</style>

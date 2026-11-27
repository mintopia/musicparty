<script setup lang="ts">
import {computed} from 'vue';
import SearchCover from '@/components/Search/SearchCover.vue';
import type {AlbumResult} from '@/types';

const props = defineProps<{album: AlbumResult; href: string}>();

const byline = computed(() =>
    [props.album.artists.map((artist) => artist.name).join(', '), props.album.release_year]
        .filter((part): part is string => !!part)
        .join(' · '),
);
</script>

<template>
    <a :href="href" class="search-tile text-reset text-decoration-none" data-test="album-tile">
        <SearchCover :src="album.image_url" :alt="album.name" icon="ti-disc" />
        <div class="fw-medium lh-sm mt-2 clamp-2" :title="album.name" data-test="album-name">{{ album.name }}</div>
        <div class="text-secondary small text-truncate" :title="byline" data-test="album-byline">{{ byline }}</div>
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

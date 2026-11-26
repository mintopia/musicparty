<script setup lang="ts">
import SearchCover from '@/components/Search/SearchCover.vue';
import type {ArtistResult} from '@/types';

defineProps<{artist: ArtistResult; href: string}>();
</script>

<template>
    <a :href="href" class="search-tile text-reset text-decoration-none text-center" data-test="artist-tile">
        <SearchCover :src="artist.image_url" :alt="artist.name" round icon="ti-user" />
        <div class="fw-medium lh-sm mt-2" data-test="artist-name">{{ artist.name }}</div>
        <div class="text-secondary small">Artist</div>
    </a>
</template>

<style scoped>
.search-tile {
    flex: 0 0 auto;
    width: 104px;
}

@media (min-width: 768px) {
    .search-tile {
        width: 130px;
    }
}
</style>

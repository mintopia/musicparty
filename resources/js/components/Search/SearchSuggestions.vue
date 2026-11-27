<script setup lang="ts">
import {computed} from 'vue';
import ArtistTile from '@/components/Search/ArtistTile.vue';
import SearchCover from '@/components/Search/SearchCover.vue';
import SearchTileRow from '@/components/Search/SearchTileRow.vue';
import type {LinkTarget, SearchSuggestions} from '@/types';

const props = defineProps<{
    suggestions: SearchSuggestions;
    searchUrl: string;
    artistRoute: (id: string) => LinkTarget;
}>();

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);
const queryUrl = (query: string): string => `${props.searchUrl}?query=${encodeURIComponent(query)}`;
const requestsLabel = (count: number): string => `${count} ${count === 1 ? 'Request' : 'Requests'}`;
const hasAny = computed(() => props.suggestions.nowPlaying !== null || props.suggestions.artists.length > 0);
</script>

<template>
    <div v-if="hasAny" class="mp-narrow mt-4 d-grid gap-4" data-test="search-suggestions">
        <section v-if="suggestions.nowPlaying" aria-labelledby="seed-h" data-test="suggest-seed">
            <div class="card">
                <div class="card-body seed">
                    <SearchCover :src="suggestions.nowPlaying.image_url" :size="64" />
                    <div class="seed-text">
                        <div id="seed-h" class="text-secondary small">Playing now</div>
                        <div class="fw-medium">{{ suggestions.nowPlaying.name }}</div>
                        <div class="text-secondary">{{ suggestions.nowPlaying.artist }}</div>
                    </div>
                    <a
                        :href="queryUrl(suggestions.nowPlaying.query)"
                        class="btn btn-outline-primary"
                        data-test="more-like"
                    >
                        <i class="icon ti ti-sparkles me-1"></i>
                        More like {{ suggestions.nowPlaying.name }}
                    </a>
                </div>
            </div>
        </section>
        <section v-if="suggestions.artists.length" aria-labelledby="tonight-h" data-test="suggest-artists">
            <div class="d-flex align-items-baseline flex-wrap column-gap-3 mb-2">
                <h2 id="tonight-h" class="section-title m-0">Most requested tonight</h2>
                <span class="text-secondary small">Artists by Requests at this party</span>
            </div>
            <SearchTileRow round>
                <ArtistTile
                    v-for="artist in suggestions.artists"
                    :key="artist.id"
                    :artist="artist"
                    :href="href(artistRoute(artist.id))"
                    :subtitle="requestsLabel(artist.requests)"
                />
            </SearchTileRow>
        </section>
    </div>
</template>

<style scoped>
.seed {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.seed-text {
    flex: 1;
    min-width: 12rem;
}

.seed .btn {
    min-height: 44px;
}

.section-title {
    font-size: 1.05rem;
}
</style>

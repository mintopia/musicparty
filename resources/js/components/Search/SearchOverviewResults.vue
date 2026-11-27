<script setup lang="ts">
import {computed} from 'vue';
import AlbumTile from '@/components/Search/AlbumTile.vue';
import ArtistTile from '@/components/Search/ArtistTile.vue';
import SearchSectionHead from '@/components/Search/SearchSectionHead.vue';
import SearchTileRow from '@/components/Search/SearchTileRow.vue';
import SearchTopResult from '@/components/Search/SearchTopResult.vue';
import TrackRow from '@/components/TrackRow.vue';
import type {LinkTarget, SearchOverviewResults, SearchType, TrackAnnotation} from '@/types';

const props = defineProps<{
    results: SearchOverviewResults;
    query?: string;
    canDownvote?: boolean;
    albumRoute: (id: string) => LinkTarget;
    artistRoute: (id: string) => LinkTarget;
    typeUrl: (type: SearchType) => string;
}>();

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
}>();

const topResult = computed(() =>
    props.results.weak && props.results.top?.kind === 'track' ? null : props.results.top,
);
const trackRows = computed(() => props.results.tracks);

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);
</script>

<template>
    <div data-test="search-overview">
        <div
            class="alert alert-warning mt-4 mb-0 notice"
            role="status"
            data-test="no-close-matches"
            v-if="results.weak"
        >
            <i class="icon ti ti-alert-circle fs-2"></i>
            <div>
                <h2 class="h4 mb-1">No close matches for “{{ query }}”</h2>
                <div>These are the nearest results we found. Check the spelling or try the artist's name.</div>
            </div>
        </div>
        <div v-if="topResult || trackRows.length" class="row g-4 mt-0">
            <div v-if="topResult" class="col-md-5 col-xxl-4">
                <SearchSectionHead title="Top result" />
                <SearchTopResult
                    :top="topResult"
                    :album-route="albumRoute"
                    :artist-route="artistRoute"
                    @request="emit('request', $event)"
                    @vote="(t, v) => emit('vote', t, v)"
                    @withdraw="emit('withdraw', $event)"
                />
            </div>
            <div
                v-if="trackRows.length"
                :class="topResult ? 'col-md-7 col-xxl-8' : 'col-12'"
                data-test="tracks-section"
            >
                <SearchSectionHead title="Tracks" :see-all-href="typeUrl('track')" see-all-label="See all tracks" />
                <div class="card">
                    <div class="list-group list-group-flush">
                        <TrackRow
                            v-for="track in trackRows"
                            :key="`${track.provider_id}:${track.id}`"
                            :track="track"
                            :can-downvote="canDownvote"
                            :album-route="albumRoute"
                            :artist-route="artistRoute"
                            compact
                            @request="emit('request', $event)"
                            @vote="(t, v) => emit('vote', t, v)"
                            @withdraw="emit('withdraw', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>
        <section v-if="results.albums.length" data-test="albums-section">
            <SearchSectionHead title="Albums" :see-all-href="typeUrl('album')" see-all-label="See all albums" />
            <SearchTileRow data-test="album-row">
                <AlbumTile
                    v-for="album in results.albums"
                    :key="album.id"
                    :album="album"
                    :href="href(albumRoute(album.id))"
                />
            </SearchTileRow>
        </section>
        <section v-if="results.artists.length" data-test="artists-section">
            <SearchSectionHead title="Artists" :see-all-href="typeUrl('artist')" see-all-label="See all artists" />
            <SearchTileRow round data-test="artist-row">
                <ArtistTile
                    v-for="artist in results.artists"
                    :key="artist.id"
                    :artist="artist"
                    :href="href(artistRoute(artist.id))"
                />
            </SearchTileRow>
        </section>
    </div>
</template>

<style scoped>
.notice {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    color: var(--tblr-body-color);
}
</style>

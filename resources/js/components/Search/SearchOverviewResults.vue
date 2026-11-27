<script setup lang="ts">
import AlbumTile from '@/components/Search/AlbumTile.vue';
import ArtistTile from '@/components/Search/ArtistTile.vue';
import SearchSectionHead from '@/components/Search/SearchSectionHead.vue';
import SearchTileRow from '@/components/Search/SearchTileRow.vue';
import SearchTopResult from '@/components/Search/SearchTopResult.vue';
import TrackRow from '@/components/TrackRow.vue';
import type {LinkTarget, SearchOverviewResults, SearchType, TrackAnnotation} from '@/types';

defineProps<{
    results: SearchOverviewResults;
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

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);
</script>

<template>
    <div data-test="search-overview">
        <div v-if="results.top || results.tracks.length" class="row g-4 mt-0">
            <div v-if="results.top" class="col-md-5 col-xxl-4">
                <SearchSectionHead title="Top result" />
                <SearchTopResult
                    :top="results.top"
                    :album-route="albumRoute"
                    :artist-route="artistRoute"
                    @request="emit('request', $event)"
                    @vote="(t, v) => emit('vote', t, v)"
                    @withdraw="emit('withdraw', $event)"
                />
            </div>
            <div
                v-if="results.tracks.length"
                :class="results.top ? 'col-md-7 col-xxl-8' : 'col-12'"
                data-test="tracks-section"
            >
                <SearchSectionHead title="Tracks" :see-all-href="typeUrl('track')" see-all-label="See all tracks" />
                <div class="card">
                    <div class="list-group list-group-flush">
                        <TrackRow
                            v-for="track in results.tracks"
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

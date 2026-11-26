<script setup lang="ts">
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import Pagination from '@/components/Pagination.vue';
import SearchEntityRow from '@/components/Search/SearchEntityRow.vue';
import TrackRow from '@/components/TrackRow.vue';
import type {
    AlbumResult,
    ArtistResult,
    LinkTarget,
    SearchTypeResults,
    TrackAnnotation,
} from '@/types';

const props = defineProps<{
    results: SearchTypeResults;
    query: string;
    overviewUrl: string;
    canDownvote?: boolean;
    loadingMore?: boolean;
    albumRoute: (id: string) => LinkTarget;
    artistRoute: (id: string) => LinkTarget;
}>();

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
    loadMore: [url: string];
}>();

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);

const labels = {track: 'Tracks', album: 'Albums', artist: 'Artists'} as const;
const label = computed(() => labels[props.results.type]);

const format = (value: number): string => value.toLocaleString('en');
const totalLabel = computed(() => `${format(props.results.total)}${props.results.capped ? '+' : ''}`);
const rangeLabel = computed(() => {
    const {from, to} = props.results.items;

    return from === null || to === null ? '' : `${format(from)}–${format(to)} of ${totalLabel.value}`;
});

const earlier = ref<(TrackAnnotation | AlbumResult | ArtistResult)[]>([]);
let appending = false;

watch(
    () => props.results.items,
    (next, previous) => {
        if (appending) {
            earlier.value = [...earlier.value, ...previous.data];
            appending = false;
        } else if (next.current_page !== previous.current_page) {
            earlier.value = [];
        }
    },
);

watch(
    () => props.results.type,
    () => {
        earlier.value = [];
    },
);

const items = computed(() => [...earlier.value, ...props.results.items.data]);
const shown = computed(() => earlier.value.length + props.results.items.data.length);
const nextUrl = computed(() => props.results.items.next_page_url);

function loadMore(): void {
    if (nextUrl.value === null) {
        return;
    }
    appending = true;
    emit('loadMore', nextUrl.value);
}

const phoneQuery = '(max-width: 767.98px)';
const isPhone = ref(false);
let media: MediaQueryList | null = null;
const syncMedia = (): void => {
    isPhone.value = media?.matches ?? false;
};

onMounted(() => {
    media = typeof window.matchMedia === 'function' ? window.matchMedia(phoneQuery) : null;
    syncMedia();
    media?.addEventListener('change', syncMedia);
});

onBeforeUnmount(() => media?.removeEventListener('change', syncMedia));

const tracks = computed(() => (props.results.type === 'track' ? (items.value as TrackAnnotation[]) : []));
const albums = computed(() => (props.results.type === 'album' ? (items.value as AlbumResult[]) : []));
const artists = computed(() => (props.results.type === 'artist' ? (items.value as ArtistResult[]) : []));

const albumSubtitle = (album: AlbumResult): string =>
    [album.album_type, album.artists.map((artist) => artist.name).join(', '), album.release_year]
        .filter((part): part is string => !!part)
        .join(' · ');
</script>

<template>
    <div data-test="search-type-view">
        <div class="mt-4">
            <a :href="overviewUrl" class="d-inline-flex align-items-center mb-2" data-test="back-to-overview">
                <i class="icon ti ti-chevron-left me-1"></i>
                All results for "{{ query }}"
            </a>
            <div class="d-flex align-items-baseline gap-2">
                <h2 class="m-0" data-test="type-title">{{ label }}</h2>
                <span class="text-secondary" data-test="type-count">{{ totalLabel }}</span>
            </div>
        </div>
        <div class="card mt-3">
            <div class="list-group list-group-flush">
                <TrackRow
                    v-for="track in tracks"
                    :key="`${track.provider_id}:${track.id}`"
                    :track="track"
                    :can-downvote="canDownvote"
                    :album-route="albumRoute"
                    :artist-route="artistRoute"
                    :compact="isPhone"
                    @request="emit('request', $event)"
                    @vote="(t, v) => emit('vote', t, v)"
                    @withdraw="emit('withdraw', $event)"
                />
                <SearchEntityRow
                    v-for="album in albums"
                    :key="album.id"
                    :href="href(albumRoute(album.id))"
                    :title="album.name"
                    :subtitle="albumSubtitle(album)"
                    :image="album.image_url"
                />
                <SearchEntityRow
                    v-for="artist in artists"
                    :key="artist.id"
                    :href="href(artistRoute(artist.id))"
                    :title="artist.name"
                    subtitle="Artist"
                    :image="artist.image_url"
                    round
                    icon="ti-user"
                />
            </div>
            <div v-if="results.items.last_page > 1" class="card-footer d-none d-md-flex align-items-center">
                <span class="text-secondary small" data-test="range">{{ rangeLabel }}</span>
                <div class="ms-auto" data-test="type-pagination">
                    <Pagination :paginator="results.items" />
                </div>
            </div>
        </div>
        <div v-if="nextUrl !== null" class="d-md-none" data-test="load-more-block">
            <button
                type="button"
                class="btn btn-outline-primary w-100 mt-3"
                :disabled="loadingMore"
                data-test="load-more"
                @click="loadMore"
            >
                Load more {{ label.toLowerCase() }}
            </button>
            <div class="text-center text-secondary small mt-2" data-test="showing">
                Showing {{ format(shown) }} of {{ totalLabel }}
            </div>
        </div>
    </div>
</template>

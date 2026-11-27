<script setup lang="ts">
import {computed} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SearchOverviewPlaceholder from '@/components/Search/SearchOverviewPlaceholder.vue';
import SearchOverviewResults from '@/components/Search/SearchOverviewResults.vue';
import SearchTypeResults from '@/components/Search/SearchTypeResults.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';
import type {LinkTarget, SearchError, SearchResults, SearchType, TrackAnnotation} from '@/types';

const props = withDefaults(
    defineProps<{
        results?: SearchResults | SearchError | null;
        type?: SearchType | null;
        query?: string;
        loading?: boolean;
        failed?: boolean;
        loadingMore?: boolean;
        canDownvote?: boolean;
        placeholderCount?: number;
        overviewUrl?: string;
        albumRoute?: (id: string) => LinkTarget;
        artistRoute?: (id: string) => LinkTarget;
        typeUrl?: (type: SearchType) => string;
    }>(),
    {
        results: null,
        type: null,
        query: '',
        loading: false,
        failed: false,
        loadingMore: false,
        canDownvote: false,
        placeholderCount: 6,
        overviewUrl: '#',
        albumRoute: (id: string) => `#${id}`,
        artistRoute: (id: string) => `#${id}`,
        typeUrl: (type: SearchType) => `#${type}`,
    },
);

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
    retry: [];
    loadMore: [url: string];
}>();

const error = computed(() => {
    if (props.failed) {
        return {code: 'error', message: 'Something went wrong while searching.', retryable: true};
    }

    return props.results !== null && 'error' in props.results ? props.results.error : null;
});

const found = computed(() => (props.results !== null && 'view' in props.results ? props.results : null));
const nothingFound = computed(() => {
    const view = found.value;

    return view !== null && (view.view === 'overview' ? view.empty : view.total === 0);
});
</script>

<template>
    <div data-test="search-results">
        <SearchOverviewPlaceholder v-if="loading && type === null" />
        <div v-else-if="loading" class="card mt-4" aria-busy="true" data-test="results-loading">
            <div class="list-group list-group-flush">
                <TrackRowPlaceholder v-for="n in placeholderCount" :key="n" />
            </div>
        </div>
        <div v-else-if="error" class="card mt-4" role="alert" data-test="results-error">
            <div class="card-body text-center py-5">
                <i class="icon ti ti-cloud-off text-secondary fs-1"></i>
                <h2 class="h3 mt-3 mb-1">Search didn't load</h2>
                <div class="text-secondary" :class="{'mb-3': error.retryable}">
                    {{ error.message }}
                    <template v-if="error.retryable">Your search is still in the box.</template>
                </div>
                <button
                    v-if="error.retryable"
                    type="button"
                    class="btn btn-primary"
                    data-test="retry"
                    @click="emit('retry')"
                >
                    <i class="icon ti ti-refresh me-1"></i>
                    Retry
                </button>
            </div>
        </div>
        <EmptyState
            v-else-if="nothingFound"
            icon="ti-search-off"
            :title="`No results for &quot;${query}&quot;`"
            description="Check the spelling or try the artist's name."
            data-test="results-empty"
        />
        <SearchOverviewResults
            v-else-if="found && found.view === 'overview'"
            :results="found"
            :can-downvote="canDownvote"
            :album-route="albumRoute"
            :artist-route="artistRoute"
            :type-url="typeUrl"
            @request="emit('request', $event)"
            @vote="(t, v) => emit('vote', t, v)"
            @withdraw="emit('withdraw', $event)"
        />
        <SearchTypeResults
            v-else-if="found && found.view === 'type'"
            :results="found"
            :query="query"
            :overview-url="overviewUrl"
            :can-downvote="canDownvote"
            :loading-more="loadingMore"
            :album-route="albumRoute"
            :artist-route="artistRoute"
            @request="emit('request', $event)"
            @vote="(t, v) => emit('vote', t, v)"
            @withdraw="emit('withdraw', $event)"
            @load-more="emit('loadMore', $event)"
        />
    </div>
</template>

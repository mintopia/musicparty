<script setup lang="ts">
import {computed} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import TrackList from '@/components/TrackList.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';
import type {LinkTarget, Paginator, SearchError, TrackAnnotation} from '@/types';

const props = withDefaults(
    defineProps<{
        results?: Paginator<TrackAnnotation> | SearchError | null;
        loading?: boolean;
        failed?: boolean;
        canDownvote?: boolean;
        placeholderCount?: number;
        albumRoute?: (id: string) => LinkTarget;
        artistRoute?: (id: string) => LinkTarget;
    }>(),
    {results: null, loading: false, failed: false, canDownvote: false, placeholderCount: 6},
);

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
    retry: [];
}>();

const error = computed(() => {
    if (props.failed) {
        return {code: 'error', message: 'Something went wrong while searching.', retryable: true};
    }

    return props.results !== null && 'error' in props.results ? props.results.error : null;
});
const tracks = computed(() => (props.results !== null && 'data' in props.results ? props.results : null));
</script>

<template>
    <div class="card" data-test="search-results">
        <div v-if="loading" class="list-group list-group-flush" aria-busy="true" data-test="results-loading">
            <TrackRowPlaceholder v-for="n in placeholderCount" :key="n" />
        </div>
        <EmptyState
            v-else-if="error"
            title="Search is unavailable"
            :description="error.message"
            data-test="results-error"
        >
            <button
                v-if="error.retryable"
                type="button"
                class="btn btn-primary"
                data-test="retry"
                @click="emit('retry')"
            >
                <i class="icon ti ti-refresh"></i>
                Retry
            </button>
        </EmptyState>
        <TrackList
            v-else-if="tracks"
            :tracks="tracks"
            :can-downvote="canDownvote"
            empty-title="No results found"
            :album-route="albumRoute"
            :artist-route="artistRoute"
            @request="emit('request', $event)"
            @withdraw="emit('withdraw', $event)"
            @vote="(t, v) => emit('vote', t, v)"
        />
    </div>
</template>

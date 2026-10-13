<script setup lang="ts">
import type {LinkTarget, Paginator, TrackAnnotation} from '@/types';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import TrackRow from '@/components/TrackRow.vue';

withDefaults(
    defineProps<{
        tracks: Paginator<TrackAnnotation>;
        canDownvote?: boolean;
        emptyTitle?: string;
        albumRoute?: (id: string) => LinkTarget;
        artistRoute?: (id: string) => LinkTarget;
    }>(),
    {canDownvote: false, emptyTitle: 'No tracks found'},
);

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
}>();
</script>

<template>
    <EmptyState v-if="tracks.data.length === 0" :title="emptyTitle" />
    <div v-else>
        <div class="list-group list-group-flush">
            <TrackRow
                v-for="track in tracks.data"
                :key="`${track.provider_id}:${track.id}`"
                :track="track"
                :can-downvote="canDownvote"
                :album-route="albumRoute"
                :artist-route="artistRoute"
                @request="emit('request', $event)"
                @vote="(t, v) => emit('vote', t, v)"
                @withdraw="emit('withdraw', $event)"
            />
        </div>
        <div class="mt-3 d-flex justify-content-center">
            <Pagination :paginator="tracks" />
        </div>
    </div>
</template>

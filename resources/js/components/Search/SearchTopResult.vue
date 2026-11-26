<script setup lang="ts">
import {computed} from 'vue';
import SearchCover from '@/components/Search/SearchCover.vue';
import type {LinkTarget, SearchTopResult, TrackAnnotation} from '@/types';

const props = defineProps<{
    top: SearchTopResult;
    albumRoute: (id: string) => LinkTarget;
    artistRoute: (id: string) => LinkTarget;
}>();

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
}>();

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);

const track = computed(() => (props.top.kind === 'track' ? props.top.track : null));
const vote = computed(() => track.value?.queue.vote ?? 0);
const isAwaiting = computed(() => track.value?.awaiting_approval === true);
const flagged = computed(() => track.value?.eligibility.outcome === 'flag');
const votes = computed(() => track.value?.queue.score ?? 0);
</script>

<template>
    <div class="card" data-test="top-result">
        <div class="card-body d-flex flex-column gap-3">
            <template v-if="top.kind === 'artist'">
                <div class="top-cover">
                    <SearchCover :src="top.artist.image_url" :alt="top.artist.name" round icon="ti-user" />
                </div>
                <div>
                    <div class="fs-1 fw-bold lh-sm" data-test="top-name">{{ top.artist.name }}</div>
                    <div class="text-secondary mt-1">Artist</div>
                </div>
                <div>
                    <a :href="href(artistRoute(top.artist.id))" class="btn btn-outline-primary" data-test="top-see-artist">
                        See artist
                        <i class="icon ti ti-chevron-right ms-1"></i>
                    </a>
                </div>
            </template>
            <template v-else-if="track">
                <a :href="href(albumRoute(track.album.id))" class="top-cover" data-test="top-art">
                    <SearchCover :src="track.album.image_url" :alt="track.album.name" />
                </a>
                <div>
                    <div class="fs-1 fw-bold lh-sm" data-test="top-name">
                        {{ track.name }}
                        <span v-if="track.explicit" class="badge bg-secondary align-middle fs-5 ms-1">Explicit</span>
                    </div>
                    <div class="text-secondary mt-1" data-test="top-artists">
                        Song ·
                        <template v-for="(artist, index) in track.artists" :key="artist.id">
                            <template v-if="index > 0">, </template>
                            <a :href="href(artistRoute(artist.id))">{{ artist.name }}</a>
                        </template>
                    </div>
                    <div v-if="track.queue.in_queue" class="text-secondary small mt-1" data-test="top-note">
                        <template v-if="track.queue.requested_by">
                            Requested by {{ track.queue.requested_by }} · {{ votes }}
                            {{ votes === 1 ? 'vote' : 'votes' }}
                        </template>
                        <template v-else>Fallback Track</template>
                    </div>
                    <div
                        v-else-if="!track.eligibility.allowed"
                        class="text-warning small mt-1"
                        data-test="top-reason"
                    >
                        <i class="icon ti ti-alert-triangle"></i>
                        {{ track.eligibility.reason }}
                    </div>
                    <div v-else-if="isAwaiting" class="text-secondary small mt-1" data-test="top-awaiting">
                        Awaiting approval
                    </div>
                </div>
                <div>
                    <button
                        v-if="track.queue.in_queue"
                        type="button"
                        class="btn btn-outline-primary"
                        data-test="top-upvote"
                        @click="emit('vote', track, vote === 1 ? 0 : 1)"
                    >
                        <i class="icon ti me-1" :class="vote === 1 ? 'ti-arrow-big-up-filled' : 'ti-arrow-big-up'"></i>
                        {{ vote === 1 ? 'Upvoted' : 'Upvote' }}
                    </button>
                    <button
                        v-else-if="isAwaiting"
                        type="button"
                        class="btn btn-outline-secondary"
                        data-test="top-withdraw"
                        @click="emit('withdraw', track)"
                    >
                        Withdraw
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-primary"
                        :disabled="!track.eligibility.allowed"
                        data-test="top-request"
                        @click="emit('request', track)"
                    >
                        <i class="icon ti ti-music-plus me-1"></i>
                        {{ flagged ? 'Request approval' : 'Add' }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.top-cover {
    display: block;
    width: 88px;
}

@media (min-width: 768px) {
    .top-cover {
        width: 120px;
    }
}
</style>

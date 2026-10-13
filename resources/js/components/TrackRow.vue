<script setup lang="ts">
import {computed} from 'vue';
import type {LinkTarget, TrackAnnotation, TrackDecoration} from '@/types';

const props = withDefaults(
    defineProps<{
        track: TrackAnnotation;
        canDownvote?: boolean;
        requesting?: boolean;
        needsApproval?: boolean;
        awaitingApproval?: boolean;
        hideActions?: boolean;
        decoration?: TrackDecoration;
        albumRoute?: (id: string) => LinkTarget;
        artistRoute?: (id: string) => LinkTarget;
    }>(),
    {canDownvote: false, requesting: false, needsApproval: false, awaitingApproval: false, hideActions: false},
);

const emit = defineEmits<{
    request: [track: TrackAnnotation];
    vote: [track: TrackAnnotation, value: -1 | 0 | 1];
    withdraw: [track: TrackAnnotation];
}>();

const href = (target: LinkTarget): string => (typeof target === 'string' ? target : target.url);

const duration = computed(() => {
    const totalSeconds = Math.floor(props.track.duration_ms / 1000);
    const seconds = totalSeconds % 60;

    return `${Math.floor(totalSeconds / 60)}:${seconds < 10 ? '0' : ''}${seconds}`;
});

const isAwaiting = computed(() => props.awaitingApproval || props.track.awaiting_approval === true);
const vote = computed(() => props.track.queue.vote ?? 0);
const displayName = computed(() => props.decoration?.displayName ?? props.track.name);
const canRequest = computed(() => props.track.eligibility.allowed && !props.requesting);
const flagged = computed(() => props.needsApproval || props.track.eligibility.outcome === 'flag');
const requestLabel = computed(() => (flagged.value ? 'Request approval' : 'Add'));

const castVote = (value: -1 | 0 | 1): void => emit('vote', props.track, value);
</script>

<template>
    <div
        class="list-group-item"
        :class="decoration?.class"
        :style="decoration?.accent ? {borderLeft: `4px solid ${decoration.accent}`} : undefined"
        data-test="track-row"
    >
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <component
                    :is="albumRoute ? 'a' : 'span'"
                    :href="albumRoute ? href(albumRoute(track.album.id)) : undefined"
                    data-test="track-art"
                >
                    <img
                        v-if="track.album.image_url"
                        :src="track.album.image_url"
                        :title="track.album.name"
                        :alt="track.album.name"
                        class="rounded"
                        width="60"
                        height="60"
                    />
                </component>
            </div>
            <div class="col">
                <div>
                    <span data-test="track-name">{{ displayName }}</span>
                    <span v-if="track.explicit" class="badge bg-secondary ms-1" data-test="explicit-badge"
                        >Explicit</span
                    >
                    <span v-if="decoration?.label" class="badge bg-azure-lt ms-1" data-test="decoration-label">{{
                        decoration.label
                    }}</span>
                </div>
                <div class="text-secondary" data-test="track-artists">
                    <template v-for="(artist, index) in track.artists" :key="artist.id">
                        <template v-if="index > 0">, </template>
                        <a v-if="artistRoute" :href="href(artistRoute(artist.id))">{{ artist.name }}</a>
                        <template v-else>{{ artist.name }}</template>
                    </template>
                </div>
                <div class="text-secondary" data-test="track-album">
                    <a v-if="albumRoute" :href="href(albumRoute(track.album.id))">{{ track.album.name }}</a>
                    <template v-else>{{ track.album.name }}</template>
                </div>
                <div class="text-muted">
                    <template v-if="track.queue.in_queue">
                        <template v-if="track.queue.requested_by">Requested by {{ track.queue.requested_by }}</template>
                        <template v-else>Fallback Track</template>
                    </template>
                    <span v-else-if="!track.eligibility.allowed" class="text-warning" data-test="reason">
                        <i class="icon ti ti-alert-triangle"></i>
                        {{ track.eligibility.reason }}
                    </span>
                    <span v-else-if="flagged" data-test="needs-approval">Needs approval</span>
                </div>
                <slot name="decoration" />
            </div>
            <div class="col-auto text-secondary">{{ duration }}</div>
            <div v-if="track.queue.in_queue" class="col-auto text-center w-8" data-test="queue-state">
                <template v-if="canDownvote">
                    <button
                        type="button"
                        class="btn btn-sm btn-link"
                        aria-label="Upvote"
                        data-test="upvote"
                        @click="castVote(vote === 1 ? 0 : 1)"
                    >
                        <i
                            class="icon ti"
                            :class="vote === 1 ? 'ti-arrow-big-up-filled text-success' : 'ti-arrow-big-up'"
                        ></i>
                    </button>
                    <span data-test="score">{{ track.queue.score }}</span>
                    <button
                        type="button"
                        class="btn btn-sm btn-link"
                        aria-label="Downvote"
                        data-test="downvote"
                        @click="castVote(vote === -1 ? 0 : -1)"
                    >
                        <i
                            class="icon ti"
                            :class="vote === -1 ? 'ti-arrow-big-down-filled text-danger' : 'ti-arrow-big-down'"
                        ></i>
                    </button>
                </template>
                <template v-else>
                    <button
                        type="button"
                        class="btn btn-sm btn-link"
                        aria-label="Like"
                        data-test="heart"
                        @click="castVote(vote === 1 ? 0 : 1)"
                    >
                        <i class="icon ti" :class="vote === 1 ? 'ti-heart-filled text-success' : 'ti-heart'"></i>
                    </button>
                    <span data-test="score">{{ track.queue.score }}</span>
                </template>
            </div>
            <div v-else-if="!hideActions" class="col-auto text-center w-8">
                <button
                    v-if="isAwaiting"
                    type="button"
                    class="btn btn-outline-secondary ms-2 w-100"
                    data-test="withdraw"
                    @click="emit('withdraw', track)"
                >
                    Withdraw
                </button>
                <button
                    v-else
                    type="button"
                    class="btn btn-primary ms-2 w-100"
                    :disabled="!canRequest"
                    data-test="request"
                    @click="emit('request', track)"
                >
                    <i class="icon ti ti-music-plus"></i>
                    {{ requestLabel }}
                </button>
            </div>
        </div>
        <div v-if="isAwaiting && !track.queue.in_queue" class="text-muted small" data-test="awaiting-approval">
            Awaiting approval
        </div>
    </div>
</template>

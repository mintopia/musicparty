<script setup lang="ts">
import {computed, ref} from 'vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import {combineArtists} from '@/lib/artists';
import type {LegacyTvPartyState} from '@/types';

const props = defineProps<{
    state: LegacyTvPartyState;
    hasLength: boolean;
    progressPercent: number;
    elapsedLabel: string;
    totalLabel: string;
}>();

const artUrl = computed(() => props.state.now?.album?.image_url ?? null);
const failedArt = ref<string | null>(null);
const showArt = computed(() => artUrl.value !== null && artUrl.value !== '' && failedArt.value !== artUrl.value);
const isCurrentTrack = computed(
    () =>
        props.state.current !== null &&
        props.state.now !== null &&
        props.state.current.provider_track_id === props.state.now.provider_track_id,
);
</script>

<template>
    <section v-if="state.now !== null" class="tv-now" aria-label="Now playing">
        <img
            v-if="showArt"
            class="tv-art"
            :src="artUrl ?? undefined"
            alt=""
            width="460"
            height="460"
            decoding="async"
            @error="failedArt = artUrl"
        />
        <div v-else class="tv-art tv-art-blank" data-test="art-blank">
            <i class="ti ti-vinyl" aria-hidden="true"></i>
        </div>
        <div class="tv-now-body">
            <h2 class="tv-track-name" :title="state.now.name" data-test="now-name">{{ state.now.name }}</h2>
            <p class="tv-artists">{{ combineArtists(state.now.artists) }}</p>
            <p v-if="state.now.album?.name" class="tv-album">{{ state.now.album.name }}</p>
            <div class="tv-spacer"></div>
            <div class="tv-by-row">
                <span v-if="isCurrentTrack" class="tv-by" data-test="requested-by">
                    <template v-if="state.current?.user != null">
                        Requested by <b>{{ state.current.user }}</b>
                    </template>
                    <template v-else>From Fallback Playlist</template>
                </span>
                <span v-else class="tv-by"></span>
                <span v-if="state.current" class="tv-score" data-test="tv-score">
                    <i class="ti ti-thumb-up" aria-hidden="true"></i>{{ state.current.rating ?? 0 }}
                </span>
            </div>
            <div v-if="hasLength" class="tv-progress-row" data-test="progress-row">
                <span class="tv-time">{{ elapsedLabel }}</span>
                <PlaybackProgress class="tv-progress" :percent="progressPercent" />
                <span class="tv-time end">{{ totalLabel }}</span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.tv-now {
    display: flex;
    align-items: stretch;
    gap: calc(32 * var(--u));
    margin-block-start: calc(22 * var(--u));
    padding-inline: calc(32 * var(--u));
}

.tv-art {
    flex: none;
    display: block;
    inline-size: calc(230 * var(--u));
    block-size: calc(230 * var(--u));
    border-radius: calc(8 * var(--u));
    box-shadow: 0 calc(14 * var(--u)) calc(40 * var(--u)) rgba(0, 0, 0, 0.5);
    object-fit: cover;
}

.tv-art-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(80 * var(--u));
}

.tv-now-body {
    flex: 1;
    min-inline-size: 0;
    display: flex;
    flex-direction: column;
}

.tv-track-name {
    margin: 0;
    color: #fff;
    font-size: calc(36 * var(--u));
    font-weight: 700;
    letter-spacing: -0.015em;
    line-height: 1.15;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tv-artists,
.tv-album {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-artists {
    margin-block-start: calc(10 * var(--u));
    font-size: calc(21 * var(--u));
    color: rgba(255, 255, 255, 0.9);
}

.tv-album {
    margin-block-start: calc(4 * var(--u));
    font-size: calc(16 * var(--u));
    color: rgba(255, 255, 255, 0.62);
}

.tv-spacer {
    flex: 1;
}

.tv-by-row {
    display: flex;
    align-items: center;
    gap: calc(14 * var(--u));
}

.tv-by {
    flex: 1;
    min-inline-size: 0;
    font-size: calc(16 * var(--u));
    color: rgba(255, 255, 255, 0.78);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-by b {
    color: #fff;
    font-weight: 600;
}

.tv-score {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: calc(8 * var(--u));
    font-size: calc(18 * var(--u));
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.tv-score .ti {
    font-size: calc(22 * var(--u));
}

.tv-progress-row {
    display: flex;
    align-items: center;
    gap: calc(14 * var(--u));
    margin-block-start: calc(14 * var(--u));
}

.tv-progress {
    flex: 1;
}

.tv-time {
    min-inline-size: 3em;
    font-size: calc(14 * var(--u));
    font-variant-numeric: tabular-nums;
    color: rgba(255, 255, 255, 0.75);
}

.tv-time.end {
    text-align: end;
}
</style>

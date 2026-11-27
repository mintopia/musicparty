<script setup lang="ts">
import {computed, ref} from 'vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import RateControl from '@/components/RateControl.vue';
import type {RatingValue} from '@/composables/useRating';
import {combineArtists} from '@/lib/artists';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    state: LegacyPartyState;
    connected: boolean;
    paused: boolean;
    hasLength: boolean;
    progressPercent: number;
    elapsedLabel: string;
    totalLabel: string;
    score: number | null | undefined;
    pressed: RatingValue;
}>();

defineEmits<{rate: [value: RatingValue]}>();

const artUrl = computed(() => props.state.now?.album?.image_url ?? null);
const failedArt = ref<string | null>(null);
const showArt = computed(() => artUrl.value !== null && artUrl.value !== '' && failedArt.value !== artUrl.value);
const artists = computed(() => combineArtists(props.state.now?.artists));
const isCurrentTrack = computed(
    () =>
        props.state.current !== null &&
        props.state.now !== null &&
        props.state.current.provider_track_id === props.state.now.provider_track_id,
);
</script>

<template>
    <div
        v-if="state.now !== null"
        class="player-main"
        :class="{'has-status': !connected || paused, 'pill-wide': !connected}"
    >
        <img
            v-if="showArt"
            class="player-art"
            :src="artUrl ?? undefined"
            alt=""
            width="168"
            height="168"
            decoding="async"
            @error="failedArt = artUrl"
        />
        <div v-else class="player-art player-art-blank" data-test="art-blank">
            <i class="ti ti-vinyl" aria-hidden="true"></i>
        </div>
        <div class="player-text">
            <div class="player-title" :title="state.now.name" data-test="now-name">
                {{ state.now.name }}
            </div>
            <p class="player-artists" :title="artists">{{ artists }}</p>
            <p v-if="state.now.album?.name" class="player-album" :title="state.now.album.name">
                {{ state.now.album.name }}
            </p>
        </div>
        <div v-if="hasLength" class="player-progress" data-test="progress-row">
            <span class="player-time">{{ elapsedLabel }}</span>
            <PlaybackProgress class="player-bar" :class="{paused}" :percent="progressPercent" />
            <span class="player-time end">{{ totalLabel }}</span>
        </div>
        <div class="player-bottom">
            <span v-if="isCurrentTrack" class="player-by" data-test="requested-by">
                <template v-if="state.current?.user">
                    Requested by <b>{{ state.current.user }}</b>
                </template>
                <template v-else>From Fallback Playlist</template>
            </span>
            <span v-else class="player-by"></span>
            <RateControl v-if="state.current" :rating="score" :pressed="pressed" @rate="$emit('rate', $event)" />
        </div>
    </div>
</template>

<style scoped>
.player-main {
    display: grid;
    grid-template-columns: var(--art) minmax(0, 1fr);
    grid-template-rows: 1fr auto auto;
    column-gap: 20px;
    padding: 20px 20px 16px;
}

.player-art {
    grid-column: 1;
    grid-row: 1 / span 3;
    display: block;
    inline-size: var(--art);
    block-size: var(--art);
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
    object-fit: cover;
}

.player-art-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(var(--art) * 0.33);
}

.player-text {
    grid-column: 2;
    grid-row: 1;
    min-inline-size: 0;
}

.player-title {
    margin: 0;
    font-size: 26px;
    font-weight: 650;
    letter-spacing: -0.01em;
    line-height: 1.2;
    color: #fff;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.has-status .player-title {
    padding-inline-end: 72px;
}

.pill-wide .player-title {
    padding-inline-end: 120px;
}

.player-artists,
.player-album {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-artists {
    margin-block-start: 4px;
    font-size: 16px;
    color: rgba(255, 255, 255, 0.88);
}

.player-album {
    margin-block-start: 2px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.62);
}

.player-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    grid-column: 2;
    grid-row: 2;
    margin-block-start: 8px;
    --playback-progress-height: 4px;
    --playback-progress-track: rgba(255, 255, 255, 0.2);
    --playback-progress-fill-color: #fff;
}

.player-bar {
    flex: 1;
}

.player-bar.paused {
    --playback-progress-fill-color: rgba(255, 255, 255, 0.55);
}

.player-time {
    min-inline-size: 2.6em;
    font-variant-numeric: tabular-nums;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.72);
}

.player-time.end {
    text-align: end;
}

.player-bottom {
    grid-column: 2;
    grid-row: 3;
    min-inline-size: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-block-start: 10px;
}

.player-by {
    flex: 1;
    min-inline-size: 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.75);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-by b {
    color: #fff;
    font-weight: 600;
}

@media (max-width: 575px) {
    .player-main {
        column-gap: 12px;
        padding: 14px 14px 12px;
    }

    .player-art {
        grid-row: 1;
    }

    .player-progress,
    .player-bottom {
        grid-column: 1 / -1;
    }

    .player-progress {
        margin-block-start: 12px;
    }

    .player-title {
        font-size: 18px;
        min-block-size: 2.4em;
    }

    .player-artists {
        font-size: 14px;
    }

    .player-album {
        font-size: 12px;
    }
}
</style>

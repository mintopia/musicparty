<script setup lang="ts">
import {computed} from 'vue';
import {formatClock} from '@/composables/useProgress';
import {combineArtists} from '@/lib/artists';
import {legacyFallbackName} from '@/lib/decoration';
import type {LegacyTvTrack} from '@/types';

const props = defineProps<{track: LegacyTvTrack}>();

const art = computed(() => props.track.album?.image_url ?? null);
const accent = computed(() =>
    props.track.decoration?.accent ? {borderTopColor: props.track.decoration.accent} : undefined,
);
</script>

<template>
    <section
        class="tv-next"
        :class="{decorated: accent !== undefined}"
        :style="accent"
        aria-label="Up next"
        data-test="up-next"
    >
        <span class="tv-next-label">Up next</span>
        <img v-if="art" class="tv-next-art" :src="art" alt="" width="112" height="112" decoding="async" />
        <span v-else class="tv-next-art tv-next-blank" aria-hidden="true">
            <i class="ti ti-vinyl"></i>
        </span>
        <span class="tv-next-meta">
            <b>{{ track.name }}</b>
            <span
                v-if="track.decoration?.label"
                class="tv-next-tag"
                :style="track.decoration.accent ? {backgroundColor: track.decoration.accent} : undefined"
                data-test="decoration-label"
                >{{ track.decoration.label }}</span
            >
            · {{ combineArtists(track.artists) }}
            <template v-if="track.user === null && track.decoration?.displayName">
                · {{ legacyFallbackName(track) }}
            </template>
        </span>
        <span class="tv-time end">{{ formatClock(track.length) }}</span>
    </section>
</template>

<style scoped>
.tv-next {
    margin-block-start: auto;
    margin-inline: calc(32 * var(--u));
    display: flex;
    align-items: center;
    gap: calc(16 * var(--u));
    padding-block-start: calc(18 * var(--u));
    border-block-start: 1px solid rgba(255, 255, 255, 0.15);
    font-size: calc(17 * var(--u));
    color: rgba(255, 255, 255, 0.78);
}

.tv-next.decorated {
    border-block-start-width: 2px;
}

.tv-next-label {
    flex: none;
    font-size: calc(14 * var(--u));
    color: rgba(255, 255, 255, 0.6);
    white-space: nowrap;
}

.tv-next-art {
    flex: none;
    inline-size: calc(56 * var(--u));
    block-size: calc(56 * var(--u));
    border-radius: calc(6 * var(--u));
    object-fit: cover;
}

.tv-next-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(28 * var(--u));
}

.tv-next-meta {
    flex: 1;
    min-inline-size: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-next-meta b {
    color: #fff;
    font-weight: 600;
}

.tv-next-tag {
    margin-inline: calc(8 * var(--u));
    padding: calc(2 * var(--u)) calc(8 * var(--u));
    border-radius: calc(4 * var(--u));
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-size: calc(13 * var(--u));
    font-weight: 600;
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

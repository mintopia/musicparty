<script setup lang="ts">
import {computed} from 'vue';
import {formatClock} from '@/composables/useProgress';
import {combineArtists} from '@/lib/artists';
import type {LegacyTrack} from '@/types';

const props = defineProps<{next: LegacyTrack | null}>();

const nextArt = computed(() => props.next?.album?.image_url ?? null);
const nextArtists = computed(() => combineArtists(props.next?.artists));
</script>

<template>
    <div class="player-next" data-test="up-next">
        <template v-if="next">
            <span class="player-next-label">Up next</span>
            <img
                v-if="nextArt"
                class="player-next-art"
                :src="nextArt"
                alt=""
                width="32"
                height="32"
                loading="lazy"
                decoding="async"
            />
            <span v-else class="player-next-art player-next-blank" aria-hidden="true">
                <i class="ti ti-vinyl"></i>
            </span>
            <span class="player-next-meta" :title="`${next.name} · ${nextArtists}`">
                <b>{{ next.name }}</b>
                <span
                    v-if="next.decoration?.label"
                    class="player-next-tag"
                    :style="next.decoration.accent ? {backgroundColor: next.decoration.accent} : undefined"
                    data-test="decoration-label"
                    >{{ next.decoration.label }}</span
                >
                · {{ nextArtists }}
            </span>
            <span class="player-time end">{{ formatClock(next.length) }}</span>
        </template>
        <span v-else class="player-next-meta" data-test="nothing-queued"> Nothing queued, search to add a track </span>
    </div>
</template>

<style scoped>
.player-next {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 20px;
    border-block-start: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 13px;
    color: rgba(255, 255, 255, 0.75);
}

.player-next-label {
    flex: none;
    color: rgba(255, 255, 255, 0.6);
}

.player-next-art {
    flex: none;
    inline-size: 32px;
    block-size: 32px;
    border-radius: 4px;
    object-fit: cover;
}

.player-next-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: 16px;
}

.player-next-meta {
    flex: 1;
    min-inline-size: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-next-meta b {
    color: #fff;
    font-weight: 550;
}

.player-next-tag {
    margin-inline: 8px;
    padding: 1px 7px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
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

@media (max-width: 575px) {
    .player-next {
        padding-inline: 14px;
    }
}
</style>

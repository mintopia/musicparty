<script setup>
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import Decorations from './Decorations.vue';
import TrackThumb from './TrackThumb.vue';
import {formatClock, formatCountdown, formatDuration, requesterLabel} from '../lib/format';

defineProps({
    upNext: {type: Object, default: null},
    playsAt: {type: Number, default: null},
    now: {type: Number, default: 0},
});
</script>

<template>
    <section aria-labelledby="up-next-heading" data-testid="up-next">
        <h2 id="up-next-heading" class="mb-3 text-base font-bold md:mb-4 md:text-lg">Up Next</h2>
        <div v-if="upNext" data-testid="up-next-card" class="flex items-center gap-3 rounded border border-border bg-surface px-3 py-3 md:gap-4 md:px-5 md:py-4">
            <TrackLink :href="upNext.track.provider_url"><TrackThumb :src="upNext.track.artwork_url" /></TrackLink>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm"><TrackLink :href="upNext.track.provider_url">{{ upNext.track.title }}</TrackLink></div>
                <div class="truncate text-sm text-muted">{{ upNext.track.artists.join(', ') }}</div>
                <div class="truncate text-xs text-muted md:text-sm">{{ requesterLabel(upNext) }}</div>
                <Decorations :decorations="upNext.decorations" class="mt-1" />
            </div>
            <div class="flex flex-col items-end gap-1 text-sm text-muted">
                <span class="tabular-nums">{{ formatDuration(upNext.track.duration_ms) }}</span>
                <span v-if="playsAt !== null" data-testid="up-next-eta" class="text-xs tabular-nums">Plays in {{ formatCountdown(playsAt, now) }} (~{{ formatClock(playsAt) }})</span>
                <span data-testid="up-next-votes">{{ upNext.score }} {{ upNext.score === 1 ? 'vote' : 'votes' }}</span>
                <span data-testid="up-next-locked" class="flex items-center gap-1 text-xs"><Icon name="lock" /><span>Locked</span></span>
            </div>
        </div>
        <p v-else data-testid="up-next-empty" class="text-sm text-muted">Nothing is up next yet.</p>
    </section>
</template>

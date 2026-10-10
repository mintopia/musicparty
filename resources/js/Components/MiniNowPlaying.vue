<script setup>
import Icon from './Icon.vue';
import Decorations from './Decorations.vue';
import RatingButtons from './RatingButtons.vue';

defineProps({
    nowPlaying: {type: Object, default: null},
    partyCode: {type: String, default: ''},
    myRating: {type: Number, default: 0},
    readOnly: {type: Boolean, default: false},
});
</script>

<template>
    <div
        data-testid="mini-now-playing"
        class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex items-center gap-3 border-t border-border bg-surface px-4 py-2 md:hidden"
    >
        <div class="min-w-0 flex-1">
            <template v-if="nowPlaying">
                <div class="truncate text-sm font-medium">{{ nowPlaying.track.title }}</div>
                <div class="truncate text-xs text-muted">{{ nowPlaying.track.artists.join(', ') }}</div>
                <Decorations :decorations="nowPlaying.decorations" />
            </template>
            <div v-else class="text-sm text-muted">Nothing playing</div>
        </div>
        <RatingButtons v-if="nowPlaying && !readOnly" class="text-text" :now-playing="nowPlaying" :party-code="partyCode" :my-rating="myRating" />
        <button
            type="button"
            disabled
            aria-label="Expand now playing"
            class="flex h-11 w-11 items-center justify-center rounded text-muted"
        >
            <Icon name="chevron" class="rotate-180" />
        </button>
    </div>
</template>

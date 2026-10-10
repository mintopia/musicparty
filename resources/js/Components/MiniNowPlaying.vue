<script setup>
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import Decorations from './Decorations.vue';
import {useRatePlay} from '../lib/rating';

const props = defineProps({
    nowPlaying: {type: Object, default: null},
    partyCode: {type: String, default: ''},
    ratablePlay: {type: Object, default: null},
    readOnly: {type: Boolean, default: false},
});

const {pending, error, rate: ratePlay} = useRatePlay(() => props.partyCode, () => props.readOnly);
const rate = (direction) => ratePlay(props.ratablePlay, direction);

const buttonClass = (active, activeColor) => [
    'flex h-11 w-11 items-center justify-center rounded hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : '',
];
</script>

<template>
    <div
        data-testid="mini-now-playing"
        class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex items-center gap-3 border-t border-border bg-surface px-4 py-2 md:hidden"
    >
        <div class="min-w-0 flex-1">
            <template v-if="nowPlaying">
                <div class="truncate text-sm font-medium"><TrackLink :href="nowPlaying.track.provider_url">{{ nowPlaying.track.title }}</TrackLink></div>
                <div class="truncate text-xs text-muted">{{ nowPlaying.track.artists.join(', ') }}</div>
                <Decorations :decorations="nowPlaying.decorations" />
            </template>
            <div v-else class="text-sm text-muted">Nothing playing</div>
        </div>
        <div v-if="ratablePlay && !readOnly" data-testid="rating" class="flex flex-col items-center text-text">
            <div class="flex items-center justify-center gap-1">
                <button
                    type="button"
                    data-testid="rating-dislike"
                    aria-label="Dislike"
                    :aria-pressed="ratablePlay.my_rating === -1"
                    :disabled="pending"
                    :class="buttonClass(ratablePlay.my_rating === -1, 'text-danger')"
                    @click="rate('down')"
                ><Icon name="thumbDown" /></button>
                <span data-testid="rating-count" class="min-w-6 text-center text-sm tabular-nums" :title="`${ratablePlay.likes ?? 0} likes, ${ratablePlay.dislikes ?? 0} dislikes`">{{ (ratablePlay.likes ?? 0) - (ratablePlay.dislikes ?? 0) }}</span>
                <button
                    type="button"
                    data-testid="rating-like"
                    aria-label="Like"
                    :aria-pressed="ratablePlay.my_rating === 1"
                    :disabled="pending"
                    :class="buttonClass(ratablePlay.my_rating === 1, 'text-accent')"
                    @click="rate('up')"
                ><Icon name="thumbUp" /></button>
            </div>
            <p v-if="error" role="alert" data-testid="rating-error" class="text-xs text-danger">{{ error }}</p>
        </div>
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

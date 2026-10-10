<script setup>
import Icon from './Icon.vue';
import Decorations from './Decorations.vue';
import ModSlot from './ModSlot.vue';
import RatingButtons from './RatingButtons.vue';
import {formatDuration, requesterLabel} from '../lib/format';
import {useRatePlay} from '../lib/rating';

const props = defineProps({
    nowPlaying: {type: Object, default: null},
    ratablePlay: {type: Object, default: null},
    myRating: {type: Number, default: 0},
    partyCode: {type: String, default: ''},
    readOnly: {type: Boolean, default: false},
});

const {error, rate: ratePlay} = useRatePlay(() => props.partyCode, () => props.readOnly);
const rate = (direction) => ratePlay(props.ratablePlay, direction);

const thumbClass = (active, activeColor) => [
    'flex h-8 w-8 items-center justify-center rounded hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : 'text-white',
];
</script>

<template>
    <section data-testid="now-playing" aria-label="Now playing" class="hidden border-b border-primary bg-black text-white md:block">
        <div v-if="nowPlaying" class="mx-auto flex max-w-3xl flex-row items-start gap-4 px-0 py-4">
            <img
                v-if="nowPlaying.track.artwork_url"
                :src="nowPlaying.track.artwork_url"
                alt=""
                class="h-[300px] w-[300px] shrink-0 object-cover"
            />
            <div v-else class="h-[300px] w-[300px] shrink-0 bg-border" aria-hidden="true"></div>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
                <h2 data-testid="now-playing-title" class="truncate text-xl font-semibold">{{ nowPlaying.track.title }}</h2>
                <div class="flex items-center gap-2 text-sm"><Icon name="user" /><span data-testid="now-playing-artist" class="truncate">{{ nowPlaying.track.artists.join(', ') }}</span></div>
                <div v-if="nowPlaying.track.album" class="flex items-center gap-2 text-sm"><Icon name="playlist" /><span class="truncate">{{ nowPlaying.track.album }}</span></div>
                <div class="flex items-center gap-2 text-sm"><Icon name="musicPlus" /><span data-testid="now-playing-requester">{{ requesterLabel(nowPlaying) }}</span></div>
                <Decorations :decorations="nowPlaying.decorations" />
                <ModSlot name="now-playing" :now-playing="nowPlaying" />
                <div class="mt-2 flex justify-end text-sm tabular-nums">{{ formatDuration(nowPlaying.track.duration_ms) }}</div>
                <div v-if="ratablePlay" data-testid="now-playing-rating" class="flex items-center justify-center gap-2">
                    <button
                        type="button"
                        data-testid="rate-dislike"
                        :aria-label="`Dislike ${ratablePlay.track.title}`"
                        :aria-pressed="ratablePlay.my_rating === -1"
                        :disabled="readOnly"
                        :class="thumbClass(ratablePlay.my_rating === -1, 'text-danger')"
                        @click="rate('down')"
                    ><Icon name="thumbDown" class="h-5 w-5" /></button>
                    <span data-testid="rating-count" class="min-w-4 text-center text-sm tabular-nums">{{ ratablePlay.likes }}</span>
                    <button
                        type="button"
                        data-testid="rate-like"
                        :aria-label="`Like ${ratablePlay.track.title}`"
                        :aria-pressed="ratablePlay.my_rating === 1"
                        :disabled="readOnly"
                        :class="thumbClass(ratablePlay.my_rating === 1, 'text-accent')"
                        @click="rate('up')"
                    ><Icon name="thumbUp" class="h-5 w-5" /></button>
                </div>
                <RatingButtons v-else-if="!readOnly" :now-playing="nowPlaying" :party-code="partyCode" :my-rating="myRating" />
                <p v-if="error" role="alert" data-testid="rating-error" class="text-center text-sm text-danger">{{ error }}</p>
            </div>
        </div>
        <p v-else data-testid="now-playing-empty" class="mx-auto max-w-3xl px-0 py-8 text-sm text-white/70">Nothing is playing right now.</p>
    </section>
</template>

<script setup>
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import Decorations from './Decorations.vue';
import NowPlayingBanner from './NowPlayingBanner.vue';
import {useRatePlay} from '../lib/rating';
import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue';

const props = defineProps({
    nowPlaying: {type: Object, default: null},
    partyCode: {type: String, default: ''},
    ratablePlay: {type: Object, default: null},
    readOnly: {type: Boolean, default: false},
    positionMs: {type: Number, default: null},
    paused: {type: Boolean, default: false},
});

const startMs = computed(() => props.positionMs ?? props.nowPlaying?.position_ms ?? null);
const isPaused = computed(() => props.paused || props.nowPlaying?.paused === true);
const duration = computed(() => props.nowPlaying?.track.duration_ms ?? 0);
const clampMs = (value) => Math.max(0, duration.value > 0 ? Math.min(duration.value, value) : value);
const baseMs = ref(startMs.value ?? 0);
const anchor = ref(Date.now());
const tick = ref(anchor.value);
let clock = null;
watch(isPaused, (nowPaused) => {
    const now = Date.now();
    if (nowPaused) {
        baseMs.value = clampMs(baseMs.value + (now - anchor.value));
    }
    anchor.value = now;
    tick.value = now;
});
watch(() => [props.nowPlaying, startMs.value], () => {
    baseMs.value = startMs.value ?? 0;
    anchor.value = Date.now();
    tick.value = anchor.value;
});
onMounted(() => {
    clock = setInterval(() => {
        tick.value = Date.now();
    }, 1000);
});
onBeforeUnmount(() => clearInterval(clock));

const elapsedMs = computed(() => {
    if (!props.nowPlaying || startMs.value === null) {
        return null;
    }

    return clampMs(baseMs.value + (isPaused.value ? 0 : tick.value - anchor.value));
});
const progressPercent = computed(() => {
    return duration.value > 0 && elapsedMs.value !== null ? Math.min(100, (elapsedMs.value / duration.value) * 100) : null;
});

const expandedPositionMs = ref(null);

const expanded = ref(false);
let savedScroll = 0;
const expand = () => {
    savedScroll = window.scrollY;
    expandedPositionMs.value = elapsedMs.value;
    expanded.value = true;
};
const collapse = async () => {
    expanded.value = false;
    await nextTick();
    window.scrollTo(0, savedScroll);
};

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
            data-testid="mini-expand"
            aria-label="Expand now playing"
            :aria-expanded="expanded"
            class="flex h-11 w-11 items-center justify-center rounded text-muted hover:bg-white/10"
            @click="expand"
        >
            <Icon name="chevron" class="rotate-180" />
        </button>
        <div
            v-if="progressPercent !== null"
            data-testid="mini-progress"
            role="progressbar"
            :aria-valuenow="Math.round(progressPercent)"
            aria-valuemin="0"
            aria-valuemax="100"
            class="absolute inset-x-0 top-0 h-[3px] bg-border"
        >
            <div class="h-full bg-primary transition-[width] duration-1000 ease-linear" :style="{width: `${progressPercent}%`}"></div>
        </div>
    </div>
    <div
        v-if="expanded"
        data-testid="mini-expanded"
        role="dialog"
        aria-modal="true"
        aria-label="Now playing"
        class="fixed inset-0 z-50 flex flex-col overflow-y-auto bg-black md:hidden"
    >
        <button
            type="button"
            data-testid="mini-collapse"
            aria-label="Close now playing"
            class="flex h-11 w-11 shrink-0 items-center justify-center self-end rounded text-white hover:bg-white/10"
            @click="collapse"
        >
            <Icon name="chevron" />
        </button>
        <NowPlayingBanner
            :now-playing="nowPlaying"
            :ratable-play="ratablePlay"
            :party-code="partyCode"
            :read-only="readOnly"
            :position-ms="expandedPositionMs"
            :paused="isPaused"
        />
    </div>
</template>

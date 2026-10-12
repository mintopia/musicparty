<script setup>
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import Decorations from './Decorations.vue';
import ModSlot from './ModSlot.vue';
import {formatDuration, requesterLabel} from '../lib/format';
import {useRatePlay} from '../lib/rating';
import {router} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';

const props = defineProps({
    nowPlaying: {type: Object, default: null},
    ratablePlay: {type: Object, default: null},
    partyCode: {type: String, default: ''},
    readOnly: {type: Boolean, default: false},
    positionMs: {type: Number, default: null},
    paused: {type: Boolean, default: false},
    canControl: {type: Boolean, default: false},
    capabilities: {type: Object, default: () => ({})},
});

const {error, rate: ratePlay} = useRatePlay(() => props.partyCode, () => props.readOnly);
const rate = (direction) => ratePlay(props.ratablePlay, direction);

const baseMs = ref(props.positionMs ?? 0);
const anchor = ref(Date.now());
const tick = ref(anchor.value);
let clock = null;
const duration = computed(() => props.nowPlaying?.track.duration_ms ?? 0);
const clampMs = (value) => Math.max(0, duration.value > 0 ? Math.min(duration.value, value) : value);
watch(() => props.paused, (isPaused) => {
    const now = Date.now();
    if (isPaused) {
        baseMs.value = clampMs(baseMs.value + (now - anchor.value));
    }
    anchor.value = now;
    tick.value = now;
});
watch(() => [props.nowPlaying, props.positionMs], () => {
    baseMs.value = props.positionMs ?? 0;
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
    if (props.positionMs === null || !props.nowPlaying) {
        return null;
    }

    return clampMs(baseMs.value + (props.paused ? 0 : tick.value - anchor.value));
});
const progressPercent = computed(() => {
    return duration.value > 0 && elapsedMs.value !== null ? Math.min(100, (elapsedMs.value / duration.value) * 100) : 0;
});

const netRating = computed(() => (props.ratablePlay?.likes ?? 0) - (props.ratablePlay?.dislikes ?? 0));
const backdropStyle = computed(() => props.nowPlaying?.track.artwork_url ? {backgroundImage: `url('${props.nowPlaying.track.artwork_url}')`} : {});

const pending = ref(false);
const controlError = ref(null);
const send = (control) => {
    if (pending.value) {
        return;
    }
    router.post(`/parties/${props.partyCode}/playback/${control}`, {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = true;
            controlError.value = null;
        },
        onError: (e) => {
            controlError.value = e.playback ?? 'Could not control playback.';
        },
        onFinish: () => {
            pending.value = false;
        },
    });
};
const toggleControl = computed(() => (props.paused ? 'play' : 'pause'));
const controlDisabled = (control) => pending.value || !props.capabilities?.[control];

const thumbClass = (active, activeColor) => [
    'flex h-8 w-8 items-center justify-center rounded hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : 'text-white',
];
</script>

<template>
    <section data-testid="now-playing" aria-label="Now playing" class="border-b border-primary text-white">
        <div class="bg-black/90 bg-cover bg-center bg-no-repeat backdrop-blur-[2px] [background-blend-mode:darken]" :style="backdropStyle">
            <div v-if="nowPlaying" class="mx-auto flex max-w-[780px] items-start gap-3 px-4 py-3 md:gap-4 md:px-0 md:py-4">
                <TrackLink v-if="nowPlaying.track.artwork_url" :href="nowPlaying.track.provider_url" class="shrink-0">
                    <img :src="nowPlaying.track.artwork_url" alt="" data-testid="now-playing-cover" class="h-24 w-24 object-cover md:h-[300px] md:w-[300px]" />
                </TrackLink>
                <div v-else class="h-24 w-24 shrink-0 bg-border md:h-[300px] md:w-[300px]" aria-hidden="true"></div>
                <div class="flex min-w-0 flex-1 flex-col gap-1 md:gap-2">
                    <h2 data-testid="now-playing-title" class="line-clamp-2 text-lg font-semibold leading-tight md:truncate md:text-xl"><TrackLink :href="nowPlaying.track.provider_url">{{ nowPlaying.track.title }}</TrackLink></h2>
                    <div class="flex items-center gap-2 text-sm"><Icon name="users" /><span data-testid="now-playing-artist" class="truncate">{{ nowPlaying.track.artists.join(', ') }}</span></div>
                    <div v-if="nowPlaying.track.album" class="flex items-center gap-2 text-sm"><Icon name="playlist" /><span class="truncate">{{ nowPlaying.track.album }}</span></div>
                    <div class="flex items-center gap-2 text-sm"><Icon name="musicPlus" /><span data-testid="now-playing-requester">{{ requesterLabel(nowPlaying) }}</span></div>
                    <Decorations :decorations="nowPlaying.decorations" />
                    <ModSlot name="now-playing" :now-playing="nowPlaying" />
                    <div v-if="elapsedMs !== null" data-testid="now-playing-progress" role="progressbar" :aria-valuenow="Math.round(progressPercent)" aria-valuemin="0" aria-valuemax="100" class="mt-1 h-[5px] w-full bg-white/20 md:mt-2">
                        <div class="h-full bg-primary transition-[width] duration-1000 ease-linear" :style="{width: `${progressPercent}%`}"></div>
                    </div>
                    <div class="flex items-center justify-between gap-1 text-sm tabular-nums">
                        <span class="w-10 shrink-0 md:w-1/4"><span v-if="elapsedMs !== null" data-testid="now-playing-elapsed">{{ formatDuration(elapsedMs) }}</span></span>
                        <div v-if="canControl" data-testid="playback-controls" class="flex items-center justify-center gap-2">
                            <button
                                type="button"
                                data-testid="playback-previous"
                                aria-label="Previous"
                                :disabled="controlDisabled('previous')"
                                :class="thumbClass(false, '')"
                                @click="send('previous')"
                            ><Icon name="previous" class="h-5 w-5" /></button>
                            <button
                                type="button"
                                data-testid="playback-toggle"
                                :aria-label="paused ? 'Play' : 'Pause'"
                                :disabled="controlDisabled(toggleControl)"
                                :class="thumbClass(false, '')"
                                @click="send(toggleControl)"
                            ><Icon :name="paused ? 'play' : 'pause'" class="h-5 w-5" /></button>
                            <button
                                type="button"
                                data-testid="playback-skip"
                                aria-label="Next"
                                :disabled="controlDisabled('skip')"
                                :class="thumbClass(false, '')"
                                @click="send('skip')"
                            ><Icon name="next" class="h-5 w-5" /></button>
                        </div>
                        <span class="w-10 shrink-0 text-right md:w-1/4" data-testid="now-playing-total">{{ formatDuration(nowPlaying.track.duration_ms) }}</span>
                    </div>
                    <p v-if="controlError" role="alert" data-testid="playback-error" class="text-center text-sm text-danger">{{ controlError }}</p>
                    <div v-if="ratablePlay" data-testid="now-playing-rating" class="hidden items-center justify-center gap-2 md:flex">
                        <button
                            type="button"
                            data-testid="rate-dislike"
                            :aria-label="`Dislike ${ratablePlay.track.title}`"
                            :aria-pressed="ratablePlay.my_rating === -1"
                            :disabled="readOnly"
                            :class="thumbClass(ratablePlay.my_rating === -1, 'text-danger')"
                            @click="rate('down')"
                        ><Icon name="thumbDown" class="h-5 w-5" /></button>
                        <span data-testid="rating-count" class="min-w-4 text-center text-sm tabular-nums" :title="`${ratablePlay.likes ?? 0} likes, ${ratablePlay.dislikes ?? 0} dislikes`">{{ netRating }}</span>
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
                    <p v-if="error" role="alert" data-testid="rating-error" class="text-center text-sm text-danger">{{ error }}</p>
                </div>
            </div>
            <p v-else data-testid="now-playing-empty" class="mx-auto flex max-w-3xl items-center gap-2 px-4 py-8 text-sm text-white/70 md:px-0"><Icon name="musicSearch" />There is nothing playing</p>
        </div>
    </section>
</template>

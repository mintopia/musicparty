<script setup lang="ts">
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import {store as storeRating} from '@/routes/api/v1/parties/playedsongs/rate';
import type {LegacyArtist, LegacyPartyState} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState}>();

const state = ref<LegacyPartyState>(props.initialState);
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

const status = computed(() => (state.value.status?.isPlaying ? state.value.status : null));
const startedAt = computed(() =>
    status.value === null ? null : new Date(status.value.updatedAt).getTime() - status.value.progress,
);
const elapsedMs = computed(() =>
    status.value === null || startedAt.value === null ? 0 : Math.min(now.value - startedAt.value, status.value.length),
);
const progress = computed(() => (status.value === null ? 0 : (elapsedMs.value / status.value.length) * 100));
const currentTime = computed(() => (status.value === null ? '' : formatMs(elapsedMs.value)));
const endTime = computed(() => (status.value === null ? '' : formatMs(status.value.length)));
const backgroundStyle = computed(() =>
    state.value.now === null ? '' : `background-image: url('${state.value.now.album.image_url}');`,
);

function formatMs(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);

    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

function combineArtists(artists: LegacyArtist[]): string {
    return artists.map((artist) => artist.name).join(', ');
}

function rate(value: number): void {
    const current = state.value.current;
    if (current === null) {
        return;
    }
    const route = storeRating({party: props.code, playedsong: current.id});
    void axios.request({url: route.url, method: route.method, data: {rating: value}});
}

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    (
        useEcho().publicChannel(`party.${props.code}`) as {
            listen(event: string, cb: (payload: LegacyPartyState) => void): void;
        }
    ).listen('Party\\UpdatedEvent', (payload) => {
        state.value = payload;
    });
});

onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});
</script>

<template>
    <div class="player p-0" data-test="party-player">
        <div class="blur-bg py-3 border-bottom border-primary text-white" :style="backgroundStyle">
            <div class="container-xl">
                <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                    <div v-if="state.now !== null" class="row">
                        <div class="col-auto">
                            <img class="albumart" :src="state.now.album.image_url" alt="" />
                        </div>
                        <div class="col">
                            <div class="fs-2 pb-3 overflow-hidden" data-test="now-name">
                                <strong>{{ state.now.name }}</strong>
                            </div>
                            <div class="pb-2 overflow-hidden">
                                <i class="icon ti ti-users"></i>
                                {{ combineArtists(state.now.artists) }}
                            </div>
                            <div class="pb-2 overflow-hidden">
                                <i class="icon ti ti-vinyl"></i>
                                {{ state.now.album.name }}
                            </div>
                            <div
                                v-if="state.current && state.current.provider_track_id === state.now.provider_track_id"
                                class="pb-3 overflow-hidden"
                            >
                                <i class="icon ti ti-music-question"></i>
                                <template v-if="state.current.user">Requested by {{ state.current.user }}</template>
                                <template v-else>Fallback Track</template>
                            </div>
                            <div class="progress">
                                <div
                                    class="progress-bar smooth-progress-bar"
                                    role="progressbar"
                                    :style="`width: ${progress}%;`"
                                ></div>
                            </div>
                            <div class="d-flex">
                                <div class="w-25">{{ currentTime }}</div>
                                <div class="w-50 text-center"></div>
                                <div class="w-25 text-end">{{ endTime }}</div>
                            </div>
                            <div v-if="state.current" class="mt-3 text-center">
                                <i
                                    class="icon ti ti-thumb-down cursor-pointer d-inline-block me-4"
                                    @click="rate(-1)"
                                ></i>
                                <span class="d-inline-block mx-2">{{ state.current.rating }}</span>
                                <i class="icon ti ti-thumb-up cursor-pointer d-inline-block ms-4" @click="rate(1)"></i>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty text-muted" data-test="nothing-playing">
                        <i class="icon icon-lg ti ti-music-off"></i>
                        There is nothing playing
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.player {
    overflow: hidden;
    padding: 1em;
}

.blur-bg {
    backdrop-filter: blur(2px);
    background-repeat: no-repeat;
    background-size: 100%;
    background-position: center center;
    background-blend-mode: darken;
    background-color: rgba(0, 0, 0, 0.9);
}

img.albumart {
    max-width: 11em;
}

.progress {
    height: 5px;
    margin-bottom: 5px;
}

.smooth-progress-bar {
    transition: width 1s linear;
}
</style>

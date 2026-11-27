<script setup lang="ts">
import axios from 'axios';
import {router} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import {useEcho} from '@/composables/useEcho';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import RateControl from '@/components/RateControl.vue';
import {useTicker} from '@/composables/useProgress';
import {store as storeRating} from '@/routes/api/v1/parties/playedsongs/rate';
import type {LegacyArtist, LegacyPartyState} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState}>();

const state = ref<LegacyPartyState>(props.initialState);
const status = computed(() => (state.value.status?.isPlaying ? state.value.status : null));
const now = useTicker(
    () => status.value !== null,
    () => state.value.status,
);
const startedAt = computed(() =>
    status.value === null ? null : new Date(status.value.sampledAt).getTime() - status.value.progressMs,
);
const elapsedMs = computed(() =>
    status.value === null || startedAt.value === null
        ? 0
        : Math.max(0, Math.min(now.value - startedAt.value, status.value.length)),
);
const progress = computed(() =>
    status.value === null || !status.value.length ? 0 : (elapsedMs.value / status.value.length) * 100,
);
const currentTime = computed(() => (status.value === null ? '' : formatMs(elapsedMs.value)));
const endTime = computed(() => (status.value === null ? '' : formatMs(status.value.length)));
const backgroundImage = computed(() => state.value.now?.album?.image_url ?? null);

function formatMs(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);

    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
}

function rate(value: number): void {
    const current = state.value.current;
    if (current === null) {
        return;
    }
    const route = storeRating({party: props.code, playedsong: current.id});
    void axios.request({url: route.url, method: route.method, data: {rating: value}});
}

watch(
    () => props.initialState,
    (next) => {
        state.value = next;
    },
);

const echo = useEcho();
(
    echo.publicChannel(`party.${props.code}`) as {
        listen(event: string, cb: (payload: LegacyPartyState) => void): void;
    }
).listen('Party\\UpdatedEvent', (payload) => {
    state.value = payload;
});
echo.onReconnect(() => router.reload({only: ['playerState']}));
</script>

<template>
    <div data-test="party-player">
        <NowPlayingBackdrop :image="backgroundImage" class="py-3 border-bottom border-primary">
            <div class="container-xl">
                <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                    <div v-if="state.now !== null" class="row">
                        <div class="col-auto">
                            <img
                                class="party-player-art"
                                :src="state.now.album?.image_url"
                                alt=""
                                width="176"
                                height="176"
                                decoding="async"
                            />
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
                                {{ state.now.album?.name }}
                            </div>
                            <div
                                v-if="state.current && state.current.provider_track_id === state.now.provider_track_id"
                                class="pb-3 overflow-hidden"
                            >
                                <i class="icon ti ti-music-question"></i>
                                <template v-if="state.current.user">Requested by {{ state.current.user }}</template>
                                <template v-else>Fallback Track</template>
                            </div>
                            <PlaybackProgress class="mb-1" :percent="progress" />
                            <div class="d-flex">
                                <div class="w-25">{{ currentTime }}</div>
                                <div class="w-50 text-center"></div>
                                <div class="w-25 text-end">{{ endTime }}</div>
                            </div>
                            <div v-if="state.current" class="mt-3 text-center">
                                <RateControl :rating="state.current.rating" @rate="rate" />
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        class="text-white"
                        title="There is nothing playing"
                        icon="ti-music-off"
                        data-test="nothing-playing"
                    />
                </div>
            </div>
        </NowPlayingBackdrop>
    </div>
</template>

<style scoped>
.party-player-art {
    width: 11rem;
    max-width: 100%;
    height: auto;
    aspect-ratio: 1;
    object-fit: cover;
}
</style>

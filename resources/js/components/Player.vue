<template>
    <div>
        <div class="player p-0">
            <div class="blur-bg py-3 border-bottom border-primary text-white" v-bind:style="backgroundImageStyle">
                <div class="container-xl">
                    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
                        <template v-if="state && state.now">
                            <div class="row">
                                <div class="col-auto">
                                    <img class="albumart" v-bind:src="state.now.album?.image_url" />
                                </div>
                                <div class="col">
                                    <div class="fs-2 pb-3 overflow-hidden">
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
                                        class="pb-3 overflow-hidden"
                                        v-if="
                                            state.current &&
                                            state.current.provider_track_id === state.now.provider_track_id
                                        "
                                    >
                                        <i class="icon ti ti-music-question"></i>
                                        <template v-if="state.current.user !== null">
                                            Requested by {{ state.current.user }}
                                        </template>
                                        <template v-else> Fallback Track </template>
                                    </div>
                                    <div class="progress">
                                        <div
                                            class="progress-bar smooth-progress-bar"
                                            role="progressbar"
                                            v-bind:style="`width: ${progress}%;`"
                                        ></div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="w-25">
                                            {{ currentTime }}
                                        </div>
                                        <div class="w-50 text-center">
                                            <!--
                                            <i @click="control('previous')" v-if="can_manage" class="icon ti ti-player-skip-back-filled cursor-pointer me-2"></i>
                                            <i @click="control('pause')" v-if="state.status.isPlaying && can_manage" class="icon ti ti-player-pause-filled cursor-pointer me-2"></i>
                                            <i @click="control('play')" v-if="!state.status.isPlaying && can_manage" class="icon ti ti-player-play-filled cursor-pointer me-2"></i>
                                            <i @click="control('next')" v-if="can_manage" class="icon ti ti-player-skip-forward-filled cursor-pointer"></i>
                                            -->
                                        </div>
                                        <div class="w-25 text-end">
                                            {{ endTime }}
                                        </div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <i
                                            class="icon ti ti-thumb-down cursor-pointer d-inline-block me-4"
                                            @click="rate(-1)"
                                        ></i>
                                        <span class="d-inline-block mx-2">{{ state.current?.rating ?? 0 }}</span>
                                        <i
                                            class="icon ti ti-thumb-up cursor-pointer d-inline-block ms-4"
                                            @click="rate(1)"
                                        ></i>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="empty text-muted">
                                <i class="icon icon-lg ti ti-music-off"></i>
                                There is nothing playing
                            </div>
                        </template>
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
<script setup lang="ts">
import {computed, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import {useTicker} from '@/composables/useProgress';
import type {LegacyArtist, LegacyPartyState} from '../types';

const props = defineProps<{
    code: string;
    initialstate: string;
    can_manage?: unknown;
}>();

const state = ref<LegacyPartyState | null>(null);

const status = computed(() => (state.value?.status?.isPlaying ? state.value.status : null));
const now = useTicker(
    () => status.value !== null,
    () => state.value,
);

function formatMs(ms: number): string {
    const mins = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    return mins + ':' + (seconds < 10 ? '0' : '') + seconds;
}

const elapsedMs = computed(() => {
    if (status.value === null) {
        return null;
    }
    return Math.max(0, now.value - (new Date(status.value.sampledAt).getTime() - status.value.progressMs));
});

const progress = computed(() =>
    status.value === null || elapsedMs.value === null || !status.value.length
        ? 0
        : Math.min((elapsedMs.value / status.value.length) * 100, 100),
);
const currentTime = computed(() =>
    status.value === null || elapsedMs.value === null ? '' : formatMs(Math.min(elapsedMs.value, status.value.length)),
);
const endTime = computed(() => (status.value === null ? '' : formatMs(status.value.length)));

const backgroundImageStyle = computed(() => {
    const image = state.value?.now?.album?.image_url;
    return image ? `background-image: url('${image}');` : '';
});

function updateState(next: LegacyPartyState): void {
    state.value = next;
}

function control(action: string): void {
    void window.axios.post(`/api/v1/parties/${props.code}/control`, {action}).then((response) => {
        updateState(response.data.data.status);
    });
}

function rate(value: number): void {
    const id = state.value?.current?.id;
    if (id === undefined) {
        return;
    }
    void window.axios.post(`/api/v1/parties/${props.code}/playedsongs/${id}/rate`, {rating: value});
}

function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
}

async function resync(): Promise<void> {
    try {
        const response = await window.axios.get(`/api/v1/parties/${props.code}`);
        updateState(response.data.data.status);
    } catch {
    }
}

updateState(JSON.parse(props.initialstate));

const echo = useEcho();
(
    echo.publicChannel(`party.${props.code}`) as {
        listen(event: string, cb: (payload: LegacyPartyState) => void): void;
    }
).listen('Party\\UpdatedEvent', updateState);
echo.onReconnect(() => void resync());

defineExpose({control});
</script>

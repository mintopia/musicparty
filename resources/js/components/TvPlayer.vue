<template>
    <div class="player p-0 vh-100 w-100 bg-black text-white">
        <div class="blur-bg p-3 vh-100 w-100" v-bind:style="backgroundStyle">
            <h1 class="d-flex mb-5">
                <div class="col">
                    {{ state.name }}
                </div>
                <div class="col-auto text-end">
                    {{ code }}
                </div>
            </h1>
            <div class="container-fluid d-flex">
                <div class="col-xl-8 offset-xl-2 mt-auto">
                    <template v-if="state.now !== null">
                        <div class="row">
                            <div class="col-auto">
                                <img class="albumart" v-bind:src="state.now.album.image_url" />
                            </div>
                            <div class="col fs-1 pt-4">
                                <div class="fs-0 pb-3 overflow-hidden">
                                    <strong>{{ state.now.name }}</strong>
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-users fs-1"></i>
                                    {{ combineArtists(state.now.artists) }}
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-vinyl fs-1"></i>
                                    {{ state.now.album.name }}
                                </div>
                                <div
                                    class="pb-2 overflow-hidden"
                                    v-if="
                                        state.current && state.current.provider_track_id === state.now.provider_track_id
                                    "
                                >
                                    <i class="icon-lg ti ti-music-question fs-1"></i>
                                    <template v-if="state.current?.user != null">
                                        Requested by {{ state.current?.user }}
                                    </template>
                                    <template v-else> Fallback Track </template>
                                </div>
                                <div class="pb-3 overflow-hidden">
                                    <i class="icon-lg fs-1 ti ti-heart"></i>
                                    {{ state.current?.rating }}
                                </div>
                                <div class="progress">
                                    <div
                                        class="progress-bar smooth-progress-bar"
                                        role="progressbar"
                                        v-bind:style="`width: ${progressPercent}%;`"
                                    ></div>
                                </div>
                                <div class="d-flex">
                                    <div class="col">
                                        {{ currentTime }}
                                    </div>
                                    <div class="col text-end">
                                        {{ endTime }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h2 v-if="state.next !== null" class="mt-4">Next</h2>
                        <div
                            v-if="state.next !== null"
                            v-bind:class="`${legacyRowClasses(state.next)} text-muted`"
                            v-bind:style="legacyAccentStyle(state.next)"
                        >
                            <div class="col-auto">
                                <img class="albumart-small" v-bind:src="state.next.album.image_url" />
                            </div>
                            <div class="col fs-1 pt-4">
                                <div class="fs-0 pb-3 overflow-hidden">
                                    <strong>{{ state.next.name }}</strong>
                                    <span
                                        v-if="state.next.decoration?.label"
                                        class="badge bg-azure-lt ms-2"
                                        data-test="decoration-label"
                                        >{{ state.next.decoration.label }}</span
                                    >
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-users fs-1"></i>
                                    {{ combineArtists(state.next.artists) }}
                                </div>
                                <div class="pb-2 overflow-hidden">
                                    <i class="icon-lg ti ti-vinyl fs-1"></i>
                                    {{ state.next.album.name }}
                                </div>
                                <div class="pb-3 overflow-hidden">
                                    <i class="icon-lg ti ti-music-question fs-1"></i>
                                    <template v-if="state.next.user !== null">
                                        Requested by {{ state.next.user }}
                                    </template>
                                    <template v-else>{{ legacyFallbackName(state.next) }}</template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="empty text-muted fs-0">
                            <i class="icon icon-lg ti ti-music-off"></i>
                            There is nothing playing
                        </div>
                    </template>
                </div>
            </div>
            <div class="qr" v-if="state.show_qrcode">
                <img v-bind:src="qrCodeUrl" />
            </div>
        </div>
    </div>
</template>
<style>
.player {
    overflow: hidden;
    padding: 1em;
}

.qr {
    position: absolute;
    bottom: 20px;
    left: 20px;
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
    max-width: 22em;
}

img.albumart-small {
    max-width: 13em;
}

.progress {
    height: 5px;
    margin-bottom: 5px;
}

.smooth-progress-bar {
    transition: width 1s linear;
}

.fs-0 {
    font-size: 2rem !important;
}
</style>
<script setup lang="ts">
import {computed} from 'vue';
import {formatClock, useProgress} from '@/composables/useProgress';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import type {LegacyArtist, LegacyTvPartyState, PlaybackState} from '@/types';

const props = defineProps<{
    code: string;
    partyUrl: string;
    state: LegacyTvPartyState;
}>();

const isPlaying = computed(() => props.state.status?.isPlaying === true);

const playback = computed<PlaybackState | null>(() => {
    const status = props.state.status;
    if (!status || !status.isPlaying) {
        return null;
    }

    return {
        isPlaying: true,
        trackId: null,
        linkedFromId: null,
        durationMs: status.length,
        progressMs: status.progressMs,
        sampledAt: status.sampledAt,
        shuffle: false,
        repeat: 'off',
        deviceId: null,
        deviceName: null,
        source: 'tv',
    };
});

const {progressMs, progressPercent} = useProgress(playback);

const currentTime = computed(() => (isPlaying.value ? formatClock(progressMs.value) : ''));
const endTime = computed(() => (isPlaying.value ? formatClock(props.state.status!.length) : ''));

const backgroundStyle = computed(() =>
    props.state.now === null ? '' : `background-image: url('${props.state.now.album.image_url}');`,
);

const qrCodeUrl = computed(() => `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${props.partyUrl}`);

function combineArtists(artists: LegacyArtist[]): string {
    return artists.map((artist) => artist.name).join(', ');
}
</script>

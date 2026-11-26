<script setup lang="ts">
import axios from 'axios';
import {computed, inject, ref} from 'vue';
import HealthGrid from '@/components/Dashboard/HealthGrid.vue';
import SeekBar from '@/components/Dashboard/SeekBar.vue';
import {useTicker} from '@/composables/useProgress';
import {notifyKey, sendControl, startPlayback, useRun} from '@/lib/dashboard';
import {store as storeRating} from '@/routes/api/v1/parties/playedsongs/rate';
import type {DashboardControl, LegacyPartyState} from '@/types';

const props = defineProps<{
    code: string;
    host: string;
    state: LegacyPartyState;
    control: DashboardControl;
    canControl: boolean;
}>();

const run = useRun();
const notify = inject(notifyKey, () => undefined);
const busy = ref(false);

const status = computed(() => props.state.status);
const playing = computed(() => status.value?.isPlaying ?? false);
const now = useTicker(playing, status);
const durationMs = computed(() => status.value?.length ?? 0);
const positionMs = computed(() => {
    const current = status.value;
    if (!current) {
        return 0;
    }
    if (!current.isPlaying) {
        return Math.min(current.progressMs, current.length);
    }
    return Math.max(0, Math.min(now.value - (Date.parse(current.sampledAt) - current.progressMs), current.length));
});
const artist = computed(() => (props.state.now?.artists ?? []).map((a) => a.name).join(', '));
const art = computed(() => props.state.now?.album?.image_url ?? '');
const inParty = computed(() => props.control.start.state === 'hidden');
const outsideLabel = computed(() => props.control.playingFrom.label ?? 'Spotify');

async function transport(action: 'play' | 'pause' | 'next' | 'previous'): Promise<void> {
    busy.value = true;
    await run(() => sendControl(props.code, action));
    busy.value = false;
}

async function seek(ms: number): Promise<void> {
    await run(() => sendControl(props.code, 'seek', {positionMs: ms}));
}

async function restartInParty(): Promise<void> {
    busy.value = true;
    await run(() => startPlayback(props.code, props.control.start.selectedDeviceId, 'restart'));
    busy.value = false;
}

async function rate(value: number): Promise<void> {
    const current = props.state.current;
    if (current === null) {
        return;
    }
    const route = storeRating({party: props.code, playedsong: current.id});
    try {
        await axios.request({url: route.url, method: route.method, data: {rating: value}});
    } catch {
        notify('That rating could not be saved.');
    }
}
</script>

<template>
    <div class="pu" data-test="player-unit">
        <div v-if="state.now !== null">
            <div class="d-flex gap-4">
                <div class="pu-art">
                    <img v-if="art" class="pu-cover" :src="art" :title="state.now.album?.name" alt="" />
                    <span v-else class="pu-cover pu-cover-blank"></span>
                </div>
                <div class="flex-grow-1 d-flex flex-column min-w-0">
                    <div class="pu-title text-truncate" :title="state.now.name" data-test="now-name">
                        {{ state.now.name }}
                    </div>
                    <div class="np-meta mt-1 text-truncate">
                        <i class="icon ti ti-users"></i> {{ artist }} <span class="mx-2">·</span
                        ><i class="icon ti ti-vinyl"></i> {{ state.now.album?.name }}
                    </div>
                    <div class="mt-2" data-test="now-origin">
                        <span v-if="control.nowPlaying.isRequest" class="np-meta small">
                            <i class="icon ti ti-music-question"></i> Requested by {{ control.nowPlaying.requestedBy }}
                        </span>
                        <span v-else class="badge bg-white-lt text-white">Not a party request</span>
                    </div>
                    <div class="mt-auto">
                        <SeekBar
                            :position-ms="positionMs"
                            :duration-ms="durationMs"
                            :disabled="!canControl"
                            @seek="seek"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="empty text-muted pu-empty" data-test="nothing-playing">
            <i class="icon icon-lg ti ti-music-off"></i>
            There is nothing playing
        </div>

        <div class="pu-row">
            <div>
                <button
                    v-if="inParty && state.now !== null"
                    type="button"
                    class="btn btn-sm btn-ondark rounded-pill px-3"
                    :title="`Start this track again in the party context on ${host}'s Spotify`"
                    :disabled="busy || !canControl"
                    data-test="restart-in-party"
                    @click="restartInParty"
                >
                    <i class="ti ti-refresh"></i><span class="restart-label ms-1">Restart in party</span>
                </button>
            </div>
            <div class="tp">
                <template v-if="state.now !== null">
                    <button
                        type="button"
                        class="btn btn-ondark"
                        aria-label="Restart track"
                        :disabled="busy || !canControl"
                        data-test="previous"
                        @click="transport('previous')"
                    >
                        <i class="ti ti-player-skip-back"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-play"
                        :aria-label="playing ? 'Pause' : 'Play'"
                        :disabled="busy || !canControl"
                        data-test="play-pause"
                        @click="transport(playing ? 'pause' : 'play')"
                    >
                        <i class="ti" :class="playing ? 'ti-player-pause' : 'ti-player-play'"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-ondark"
                        aria-label="Skip"
                        :disabled="busy || !canControl"
                        data-test="next"
                        @click="transport('next')"
                    >
                        <i class="ti ti-player-skip-forward"></i>
                    </button>
                </template>
                <button
                    v-else
                    type="button"
                    class="btn btn-primary btn-play"
                    aria-label="Play"
                    :disabled="busy || !canControl"
                    data-test="play-pause"
                    @click="transport('play')"
                >
                    <i class="ti ti-player-play"></i>
                </button>
            </div>
            <div class="pu-rate">
                <template v-if="state.current">
                    <button type="button" class="btn btn-ondark" aria-label="Rate down" @click="rate(-1)">
                        <i class="ti ti-thumb-down"></i>
                    </button>
                    <span class="mx-2" data-test="rating">{{ state.current.rating ?? 0 }}</span>
                    <button type="button" class="btn btn-ondark" aria-label="Rate up" @click="rate(1)">
                        <i class="ti ti-thumb-up"></i>
                    </button>
                </template>
            </div>
        </div>

        <div v-if="control.playingFrom.kind === 'outside'" class="pu-hint" data-test="skip-hint">
            <i class="ti ti-info-circle"></i>
            <span>Skip moves through {{ outsideLabel }}. Use Start Playback to put the party back on.</span>
        </div>

        <HealthGrid :health="control.health" :playing-from="control.playingFrom" />
    </div>
</template>

<style scoped>
.pu {
    container-type: inline-size;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    padding: 20px;
    color: #fff;
    height: 100%;
}
.pu-cover {
    display: block;
    width: 168px;
    height: 168px;
    border-radius: 4px;
    object-fit: cover;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}
.pu-cover-blank {
    background: rgba(255, 255, 255, 0.1);
}
.pu-title {
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.01em;
}
.np-meta {
    color: rgba(255, 255, 255, 0.78);
}
.np-meta .ti {
    opacity: 0.8;
}
.pu-empty {
    padding: 2rem 0;
}
.pu-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    margin-top: 10px;
}
.tp {
    display: flex;
    align-items: center;
    gap: 18px;
}
.tp .btn {
    border-radius: 50%;
    padding: 0;
    display: inline-grid;
    place-items: center;
    width: 60px;
    height: 60px;
}
.tp .btn .ti {
    font-size: 1.8rem;
}
.tp .btn-play {
    width: 80px;
    height: 80px;
}
.tp .btn-play .ti {
    font-size: 2.4rem;
}
.btn-ondark {
    --tblr-btn-bg: rgba(255, 255, 255, 0.1);
    --tblr-btn-border-color: rgba(255, 255, 255, 0.22);
    --tblr-btn-color: #fff;
    --tblr-btn-hover-bg: rgba(255, 255, 255, 0.2);
    --tblr-btn-hover-border-color: rgba(255, 255, 255, 0.35);
    --tblr-btn-hover-color: #fff;
    --tblr-btn-disabled-color: rgba(255, 255, 255, 0.4);
}
.pu-rate {
    justify-self: end;
    display: flex;
    align-items: center;
    gap: 4px;
    color: rgba(255, 255, 255, 0.8);
}
.pu-rate .btn {
    width: 40px;
    height: 40px;
    padding: 0;
    border-radius: 50%;
}
.pu-rate .ti {
    font-size: 1.3rem;
}
.pu-hint {
    margin-top: 12px;
    font-size: 0.8rem;
    color: #fde68a;
    display: flex;
    gap: 6px;
    align-items: flex-start;
}
@container (max-width: 620px) {
    .pu-cover {
        width: 112px;
        height: 112px;
    }
    .pu-title {
        font-size: 1.5rem;
    }
    .restart-label {
        display: none;
    }
    .tp {
        gap: 12px;
    }
    .tp .btn {
        width: 52px;
        height: 52px;
    }
    .tp .btn-play {
        width: 68px;
        height: 68px;
    }
}
</style>

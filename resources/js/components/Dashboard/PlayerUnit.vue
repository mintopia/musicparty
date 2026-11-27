<script setup lang="ts">
import {computed, inject, ref} from 'vue';
import HealthGrid from '@/components/Dashboard/HealthGrid.vue';
import SeekBar from '@/components/Dashboard/SeekBar.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import RateControl from '@/components/RateControl.vue';
import {useTicker} from '@/composables/useProgress';
import {useRating} from '@/composables/useRating';
import {notifyKey, sendControl, useRun} from '@/lib/dashboard';
import type {PlaybackStatus} from '@/lib/playbackStatus';
import type {DashboardControl, LegacyPartyState, MyRating} from '@/types';

const props = withDefaults(
    defineProps<{
        code: string;
        state: LegacyPartyState;
        control: DashboardControl;
        status: PlaybackStatus;
        webPlayerUrl: string | null;
        relinkUrl: string;
        canControl: boolean;
        myRating?: MyRating | null;
        connected?: boolean;
    }>(),
    {myRating: null, connected: true},
);

const run = useRun();
const notify = inject(notifyKey, () => undefined);
const busy = ref(false);

const playback = computed(() => props.state.status);
const playing = computed(() => playback.value?.isPlaying ?? false);
const now = useTicker(playing, playback);
const durationMs = computed(() => playback.value?.length ?? 0);
const positionMs = computed(() => {
    const current = playback.value;
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
const showTrack = computed(
    () => props.state.now !== null && ['playing', 'paused', 'outside'].includes(props.status.kind),
);
const outside = computed(() => props.status.kind === 'outside');
const outsideLabel = computed(() => props.control.playingFrom.label ?? 'Spotify');

async function transport(action: 'play' | 'pause' | 'next' | 'previous'): Promise<void> {
    busy.value = true;
    await run(() => sendControl(props.code, action));
    busy.value = false;
}

async function seek(ms: number): Promise<void> {
    await run(() => sendControl(props.code, 'seek', {positionMs: ms}));
}

const {pressed, score, rate} = useRating({
    code: props.code,
    state: () => props.state,
    myRating: () => props.myRating,
    onError: notify,
});
</script>

<template>
    <div class="pu" data-test="player-unit">
        <div class="pu-status" role="status" aria-live="polite">
            <PlayerStatusPill v-if="!connected" warn data-test="reconnecting">Reconnecting…</PlayerStatusPill>
        </div>
        <div class="pu-head" :class="`is-${status.tone}`" data-test="playback-status" aria-live="polite">
            <span v-if="!showTrack" class="pu-tile"><i class="ti ti-music-off" aria-hidden="true"></i></span>
            <div class="min-w-0">
                <h2 class="pu-headline" data-test="status-headline">
                    <span v-if="status.kind === 'playing'" class="mp-eq" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span v-else class="pu-dot" aria-hidden="true"></span>
                    {{ status.headline }}
                </h2>
                <p class="pu-detail" data-test="status-detail">{{ status.detail }}</p>
            </div>
        </div>
        <div v-if="showTrack && state.now" class="mt-3">
            <div class="d-flex gap-4">
                <div class="pu-art">
                    <img v-if="art" class="pu-cover" :src="art" :title="state.now.album?.name" alt="" />
                    <span v-else class="pu-cover pu-cover-blank"></span>
                </div>
                <div class="pu-body flex-grow-1 d-flex flex-column">
                    <div class="pu-title text-truncate" :title="state.now.name" data-test="now-name">
                        {{ state.now.name }}
                    </div>
                    <div class="np-meta mt-1 text-truncate" :title="artist" data-test="now-artists">
                        {{ artist }}
                    </div>
                    <div class="np-meta text-truncate" :title="state.now.album?.name" data-test="now-album">
                        {{ state.now.album?.name }}
                    </div>
                    <div class="mt-2" data-test="now-origin">
                        <span v-if="control.nowPlaying.isRequest" class="np-meta small">
                            Requested by {{ control.nowPlaying.requestedBy }}
                        </span>
                        <span v-else-if="control.nowPlaying.isFallback" class="np-meta small" data-test="now-fallback">
                            From the party’s Fallback Playlist
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

        <div v-if="showTrack" class="pu-row">
            <div></div>
            <div class="tp">
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
                    class="btn btn-play"
                    :class="outside ? 'btn-ondark' : 'btn-primary'"
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
            </div>
            <div class="pu-rate">
                <RateControl v-if="state.current" :rating="score" :pressed="pressed" @rate="rate" />
            </div>
        </div>

        <div v-if="outside" class="pu-hint" data-test="skip-hint">
            <i class="ti ti-info-circle"></i>
            <span>Skip moves through {{ outsideLabel }}. Use Start Playback to put the party back on.</span>
        </div>

        <HealthGrid :health="control.health" :status="status" :web-player-url="webPlayerUrl" :relink-url="relinkUrl" />
    </div>
</template>

<style scoped>
.pu {
    container-type: inline-size;
    background: var(--mp-deck-surface);
    border: 1px solid var(--mp-deck-border);
    border-radius: 14px;
    padding: 20px;
    color: var(--mp-deck-text);
    --tblr-secondary: var(--mp-deck-muted);
    --tblr-muted: var(--mp-deck-muted);
    height: 100%;
    position: relative;
}
.pu-status {
    position: absolute;
    inset-block-start: 12px;
    inset-inline-end: 14px;
    z-index: 1;
}
.pu-art {
    flex: 0 0 auto;
}
.pu-body {
    min-width: 0;
}
.pu-cover {
    display: block;
    width: 168px;
    height: 168px;
    border-radius: 4px;
    object-fit: cover;
    box-shadow: inset 0 0 0 1px var(--mp-deck-cover-edge);
}
.pu-cover-blank {
    background: var(--mp-deck-raised);
}
.pu-title {
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.01em;
}
.np-meta {
    color: var(--mp-deck-muted);
}
.pu-head {
    display: flex;
    align-items: center;
    gap: 16px;
    --mp-state: var(--tblr-secondary);
}
.pu-head.is-playing {
    --mp-state: var(--tblr-success);
}
.pu-head.is-paused,
.pu-head.is-warn {
    --mp-state: var(--tblr-warning);
}
.pu-head.is-stale,
.pu-head.is-error {
    --mp-state: var(--tblr-danger);
}
.pu-tile {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 88px;
    height: 88px;
    border-radius: 14px;
    background: var(--mp-deck-raised);
    color: var(--mp-deck-accent-warn);
    font-size: 2.4rem;
}
.pu-headline {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    font-size: 2.6rem;
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -0.02em;
    color: var(--mp-deck-text);
}
.pu-headline .mp-eq {
    block-size: 36px;
    gap: 3px;
}
.pu-headline .mp-eq > i {
    inline-size: 6px;
}
.pu-dot {
    flex: 0 0 auto;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--mp-state);
}
.pu-detail {
    margin: 4px 0 0;
    color: var(--mp-deck-muted);
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
    --tblr-btn-bg: var(--mp-deck-raised);
    --tblr-btn-border-color: var(--mp-deck-border-strong);
    --tblr-btn-color: var(--mp-deck-text);
    --tblr-btn-hover-bg: var(--mp-deck-raised-hover);
    --tblr-btn-hover-border-color: var(--mp-deck-border-strong);
    --tblr-btn-hover-color: var(--mp-deck-text);
    --tblr-btn-disabled-color: var(--mp-deck-disabled);
}
.pu-rate {
    justify-self: end;
    display: flex;
    align-items: center;
    --rate-bg: var(--mp-deck-raised);
    --rate-fg: var(--mp-deck-text);
    --rate-divider: var(--mp-deck-border-strong);
    --rate-pressed-bg: var(--mp-deck-text);
    --rate-pressed-fg: var(--mp-deck-surface);
}
.pu-hint {
    margin-top: 12px;
    font-size: 0.8rem;
    color: var(--mp-deck-accent-warn);
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
    .pu-headline {
        font-size: 2rem;
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

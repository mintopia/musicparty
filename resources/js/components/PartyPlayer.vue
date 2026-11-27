<script setup lang="ts">
import {router} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import RateControl from '@/components/RateControl.vue';
import Toast from '@/components/Toast.vue';
import {useEcho} from '@/composables/useEcho';
import {formatClock, useStatusProgress} from '@/composables/useProgress';
import {useRating} from '@/composables/useRating';
import type {LegacyArtist, LegacyPartyState, MyRating} from '@/types';

const props = defineProps<{code: string; initialState: LegacyPartyState; myRating?: MyRating | null}>();

const state = ref<LegacyPartyState>(props.initialState);
const {hasLength, paused, progressPercent, elapsedLabel, totalLabel} = useStatusProgress(() => state.value.status);
const backgroundImage = computed(() => state.value.now?.album?.image_url ?? null);
const artUrl = computed(() => state.value.now?.album?.image_url ?? null);
const failedArt = ref<string | null>(null);
const showArt = computed(() => artUrl.value !== null && artUrl.value !== '' && failedArt.value !== artUrl.value);
const nextArt = computed(() => state.value.next?.album?.image_url ?? null);
const artists = computed(() => combineArtists(state.value.now?.artists));
const nextArtists = computed(() => combineArtists(state.value.next?.artists));
const isCurrentTrack = computed(
    () =>
        state.value.current !== null &&
        state.value.now !== null &&
        state.value.current.provider_track_id === state.value.now.provider_track_id,
);

const notice = ref<string | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | null = null;

function clearNotice(): void {
    notice.value = null;
    if (noticeTimer !== null) {
        clearTimeout(noticeTimer);
        noticeTimer = null;
    }
}

function notify(message: string): void {
    clearNotice();
    notice.value = message;
    noticeTimer = setTimeout(clearNotice, 4000);
}

const {pressed, score, rate} = useRating({
    code: props.code,
    state,
    myRating: () => props.myRating,
    onError: notify,
});

function combineArtists(list: LegacyArtist[] | undefined): string {
    return (list ?? []).map((artist) => artist.name).join(', ');
}

watch(
    () => props.initialState,
    (next) => {
        state.value = next;
    },
);

const echo = useEcho();
const connected = echo.trackConnection();
(
    echo.publicChannel(`party.${props.code}`) as {
        listen(event: string, cb: (payload: LegacyPartyState) => void): void;
    }
).listen('Party\\UpdatedEvent', (payload) => {
    state.value = payload;
});
echo.onReconnect(() => router.reload({only: ['playerState', 'myRating']}));

onBeforeUnmount(clearNotice);
</script>

<template>
    <div data-test="party-player">
        <NowPlayingBackdrop :image="backgroundImage" class="player-band border-bottom border-primary">
            <div class="container-xl">
                <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1 player-frame">
                    <div class="player-status" role="status" aria-live="polite">
                        <PlayerStatusPill v-if="!connected" warn data-test="reconnecting"
                            >Reconnecting…</PlayerStatusPill
                        >
                        <PlayerStatusPill v-else-if="paused && state.now !== null" data-test="paused">
                            Paused
                        </PlayerStatusPill>
                    </div>

                    <div v-if="state.now !== null" class="player-main" :class="{'has-status': !connected || paused}">
                        <img
                            v-if="showArt"
                            class="player-art"
                            :src="artUrl ?? undefined"
                            alt=""
                            width="168"
                            height="168"
                            decoding="async"
                            @error="failedArt = artUrl"
                        />
                        <div v-else class="player-art player-art-blank" data-test="art-blank">
                            <i class="ti ti-vinyl" aria-hidden="true"></i>
                        </div>
                        <div class="player-col">
                            <h2 class="player-title" :title="state.now.name" data-test="now-name">
                                {{ state.now.name }}
                            </h2>
                            <p class="player-artists" :title="artists">{{ artists }}</p>
                            <p v-if="state.now.album?.name" class="player-album" :title="state.now.album.name">
                                {{ state.now.album.name }}
                            </p>
                            <div class="player-spacer"></div>
                            <div v-if="hasLength" class="player-progress" data-test="progress-row">
                                <span class="player-time">{{ elapsedLabel }}</span>
                                <PlaybackProgress class="player-bar" :class="{paused}" :percent="progressPercent" />
                                <span class="player-time end">{{ totalLabel }}</span>
                            </div>
                            <div class="player-bottom">
                                <span v-if="isCurrentTrack" class="player-by" data-test="requested-by">
                                    <template v-if="state.current?.user">
                                        Requested by <b>{{ state.current.user }}</b>
                                    </template>
                                    <template v-else>Fallback Track</template>
                                </span>
                                <span v-else class="player-by"></span>
                                <RateControl v-if="state.current" :rating="score" :pressed="pressed" @rate="rate" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="player-empty" data-test="nothing-playing">
                        <i class="ti ti-music-off player-empty-icon" aria-hidden="true"></i>
                        <h2 class="player-empty-title">Nothing playing</h2>
                        <p>Search for a track to get the party started.</p>
                    </div>

                    <div class="player-next" data-test="up-next">
                        <template v-if="state.next">
                            <span class="player-next-label">Up next</span>
                            <img
                                v-if="nextArt"
                                class="player-next-art"
                                :src="nextArt"
                                alt=""
                                width="32"
                                height="32"
                                loading="lazy"
                                decoding="async"
                            />
                            <span v-else class="player-next-art player-next-blank" aria-hidden="true"></span>
                            <span class="player-next-meta" :title="`${state.next.name} · ${nextArtists}`">
                                <b>{{ state.next.name }}</b>
                                <span
                                    v-if="state.next.decoration?.label"
                                    class="player-next-tag"
                                    :style="
                                        state.next.decoration.accent
                                            ? {backgroundColor: state.next.decoration.accent}
                                            : undefined
                                    "
                                    data-test="decoration-label"
                                    >{{ state.next.decoration.label }}</span
                                >
                                · {{ nextArtists }}
                            </span>
                            <span class="player-time end">{{ formatClock(state.next.length) }}</span>
                        </template>
                        <span v-else class="player-next-meta" data-test="nothing-queued">
                            Nothing queued, search to add a track
                        </span>
                    </div>
                </div>
            </div>
        </NowPlayingBackdrop>
        <Toast v-if="notice" :message="notice" variant="danger" data-test="rating-notice" @dismiss="clearNotice" />
    </div>
</template>

<style scoped>
.player-frame {
    --art: 168px;
    position: relative;
    font-family: var(--mp-font-family, inherit);
}

.player-status {
    position: absolute;
    inset-block-start: 12px;
    inset-inline-end: 14px;
    z-index: 2;
}

.player-main {
    display: flex;
    align-items: stretch;
    gap: 20px;
    padding: 20px 20px 16px;
}

.player-art {
    flex: none;
    display: block;
    inline-size: var(--art);
    block-size: var(--art);
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
    object-fit: cover;
}

.player-art-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(var(--art) * 0.33);
}

.player-col {
    flex: 1;
    min-inline-size: 0;
    display: flex;
    flex-direction: column;
}

.player-title {
    margin: 0;
    font-size: 26px;
    font-weight: 650;
    letter-spacing: -0.01em;
    line-height: 1.2;
    color: #fff;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.has-status .player-title {
    padding-inline-end: 120px;
}

.player-artists,
.player-album {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-artists {
    margin-block-start: 4px;
    font-size: 16px;
    color: rgba(255, 255, 255, 0.88);
}

.player-album {
    margin-block-start: 2px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.62);
}

.player-spacer {
    flex: 1;
}

.player-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-block-start: 8px;
    --playback-progress-height: 4px;
    --playback-progress-track: rgba(255, 255, 255, 0.2);
    --playback-progress-fill-color: #fff;
}

.player-bar {
    flex: 1;
}

.player-bar.paused {
    --playback-progress-fill-color: rgba(255, 255, 255, 0.55);
}

.player-time {
    min-inline-size: 2.6em;
    font-variant-numeric: tabular-nums;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.72);
}

.player-time.end {
    text-align: end;
}

.player-bottom {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-block-start: 10px;
}

.player-by {
    flex: 1;
    min-inline-size: 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.75);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-by b {
    color: #fff;
    font-weight: 600;
}

.player-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-block-size: calc(var(--art) + 36px);
    padding: 20px;
    text-align: center;
}

.player-empty-icon {
    font-size: 40px;
    color: rgba(255, 255, 255, 0.55);
}

.player-empty-title {
    margin: 8px 0 0;
    font-size: 20px;
    font-weight: 650;
    color: #fff;
}

.player-empty p {
    margin: 6px 0 0;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.7);
}

.player-next {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 20px;
    border-block-start: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 13px;
    color: rgba(255, 255, 255, 0.75);
}

.player-next-label {
    flex: none;
    color: rgba(255, 255, 255, 0.6);
}

.player-next-art {
    flex: none;
    inline-size: 32px;
    block-size: 32px;
    border-radius: 4px;
    object-fit: cover;
}

.player-next-blank {
    background: rgba(255, 255, 255, 0.08);
}

.player-next-meta {
    flex: 1;
    min-inline-size: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.player-next-meta b {
    color: #fff;
    font-weight: 550;
}

.player-next-tag {
    margin-inline: 8px;
    padding: 1px 7px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
}

@media (max-width: 575px) {
    .player-frame {
        --art: 84px;
    }

    .player-main {
        gap: 12px;
        padding: 14px 14px 12px;
    }

    .player-title {
        font-size: 18px;
    }

    .player-artists {
        font-size: 14px;
    }

    .player-album {
        font-size: 12px;
    }

    .player-next {
        padding-inline: 14px;
    }

    .player-main.has-status {
        padding-block-start: 36px;
    }

    .has-status .player-title {
        padding-inline-end: 0;
    }

    .player-status {
        inset-block-start: 6px;
        inset-inline-end: 8px;
    }
}
</style>

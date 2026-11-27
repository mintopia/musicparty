<script setup lang="ts">
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import {formatClock, useStatusProgress} from '@/composables/useProgress';
import {useWakeLock} from '@/composables/useWakeLock';
import {legacyFallbackName} from '@/lib/decoration';
import type {LegacyArtist, LegacyTvPartyState} from '@/types';

const STALE_AFTER_MS = 60000;

const props = withDefaults(
    defineProps<{
        code: string;
        url?: string;
        qrSvg?: string;
        state: LegacyTvPartyState;
        connected?: boolean;
    }>(),
    {url: undefined, qrSvg: undefined, connected: true},
);

useWakeLock();

const {hasLength, paused, progressPercent, elapsedLabel, totalLabel} = useStatusProgress(() => props.state.status);

const stale = ref(false);
let staleTimer: ReturnType<typeof setTimeout> | null = null;

function clearStaleTimer(): void {
    if (staleTimer !== null) {
        clearTimeout(staleTimer);
        staleTimer = null;
    }
}

watch(
    () => props.connected,
    (connected) => {
        clearStaleTimer();
        stale.value = false;
        if (!connected) {
            staleTimer = setTimeout(() => {
                staleTimer = null;
                stale.value = true;
            }, STALE_AFTER_MS);
        }
    },
    {immediate: true},
);

onBeforeUnmount(clearStaleTimer);

const backgroundImage = computed(() => props.state.now?.album?.image_url ?? null);
const artUrl = computed(() => props.state.now?.album?.image_url ?? null);
const failedArt = ref<string | null>(null);
const showArt = computed(() => artUrl.value !== null && artUrl.value !== '' && failedArt.value !== artUrl.value);
const nextArt = computed(() => props.state.next?.album?.image_url ?? null);
const isCurrentTrack = computed(
    () =>
        props.state.current !== null &&
        props.state.now !== null &&
        props.state.current.provider_track_id === props.state.now.provider_track_id,
);
const showQr = computed(() => props.state.show_qrcode && Boolean(props.qrSvg));
const qrCodeSrc = computed(() =>
    props.qrSvg ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(props.qrSvg)}` : null,
);
const joinHost = computed(() => (props.url ? props.url.replace(/^https?:\/\//, '') : null));
const nowPlaying = computed(() => props.state.now !== null);
const nextTrack = computed(() => props.state.next);
const nextAccent = computed(() =>
    nextTrack.value?.decoration?.accent ? {borderTopColor: nextTrack.value.decoration.accent} : undefined,
);

function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
}
</script>

<template>
    <NowPlayingBackdrop :image="backgroundImage" class="tv" data-test="tv-player">
        <header class="tv-header">
            <div class="tv-title-col">
                <h1 class="tv-party-name" :title="state.name">{{ state.name }}</h1>
                <div class="tv-status" role="status" aria-live="polite">
                    <PlayerStatusPill v-if="!connected" warn data-test="reconnecting">
                        {{ stale ? 'Reconnecting… showing the last known track' : 'Reconnecting…' }}
                    </PlayerStatusPill>
                    <PlayerStatusPill v-else-if="paused && nowPlaying" data-test="paused">Paused</PlayerStatusPill>
                </div>
            </div>
            <section v-if="nowPlaying" class="tv-join" aria-label="Join this party">
                <span class="tv-join-text">
                    <span class="tv-join-label">{{ showQr ? 'Scan or join with code' : 'Join with code' }}</span>
                    <b class="tv-join-code" data-test="join-code">{{ code }}</b>
                    <span v-if="joinHost" class="tv-join-url" data-test="join-url">{{ joinHost }}</span>
                </span>
                <img
                    v-if="showQr"
                    class="tv-qr tv-qr-small"
                    :src="qrCodeSrc ?? undefined"
                    :alt="`Scan to join ${state.name}`"
                    width="152"
                    height="152"
                    decoding="async"
                    data-test="tv-qr"
                />
            </section>
        </header>

        <div class="tv-stage">
            <section v-if="state.now !== null" class="tv-now" aria-label="Now playing">
                <img
                    v-if="showArt"
                    class="tv-art"
                    :src="artUrl ?? undefined"
                    alt=""
                    width="460"
                    height="460"
                    decoding="async"
                    @error="failedArt = artUrl"
                />
                <div v-else class="tv-art tv-art-blank" data-test="art-blank">
                    <i class="ti ti-vinyl" aria-hidden="true"></i>
                </div>
                <div class="tv-now-body">
                    <h2 class="tv-track-name" :title="state.now.name" data-test="now-name">{{ state.now.name }}</h2>
                    <p class="tv-artists">{{ combineArtists(state.now.artists) }}</p>
                    <p v-if="state.now.album?.name" class="tv-album">{{ state.now.album.name }}</p>
                    <div class="tv-spacer"></div>
                    <div class="tv-by-row">
                        <span v-if="isCurrentTrack" class="tv-by" data-test="requested-by">
                            <template v-if="state.current?.user != null">
                                Requested by <b>{{ state.current.user }}</b>
                            </template>
                            <template v-else>From Fallback Playlist</template>
                        </span>
                        <span v-else class="tv-by"></span>
                        <span v-if="state.current" class="tv-score" data-test="tv-score">
                            <i class="ti ti-thumb-up" aria-hidden="true"></i>{{ state.current.rating ?? 0 }}
                        </span>
                    </div>
                    <div v-if="hasLength" class="tv-progress-row" data-test="progress-row">
                        <span class="tv-time">{{ elapsedLabel }}</span>
                        <PlaybackProgress class="tv-progress" :percent="progressPercent" />
                        <span class="tv-time end">{{ totalLabel }}</span>
                    </div>
                </div>
            </section>

            <section v-else class="tv-empty" aria-label="Nothing playing" data-test="nothing-playing">
                <div class="tv-empty-copy">
                    <h2 class="tv-empty-title">Nothing playing</h2>
                    <p>
                        {{
                            showQr
                                ? 'Scan to add the first track, or join with code'
                                : 'Join with code to add the first track'
                        }}
                    </p>
                    <b class="tv-empty-code" data-test="join-code">{{ code }}</b>
                    <span v-if="joinHost" class="tv-join-url tv-empty-url" data-test="join-url">{{ joinHost }}</span>
                </div>
                <img
                    v-if="showQr"
                    class="tv-qr tv-qr-large"
                    :src="qrCodeSrc ?? undefined"
                    :alt="`Scan to join ${state.name}`"
                    width="240"
                    height="240"
                    decoding="async"
                    data-test="tv-qr"
                />
            </section>

            <section
                v-if="state.now !== null && nextTrack !== null"
                class="tv-next"
                :class="{decorated: nextAccent !== undefined}"
                :style="nextAccent"
                aria-label="Up next"
                data-test="up-next"
            >
                <span class="tv-next-label">Up next</span>
                <img
                    v-if="nextArt"
                    class="tv-next-art"
                    :src="nextArt"
                    alt=""
                    width="112"
                    height="112"
                    decoding="async"
                />
                <span v-else class="tv-next-art tv-next-blank" aria-hidden="true">
                    <i class="ti ti-vinyl"></i>
                </span>
                <span class="tv-next-meta">
                    <b>{{ nextTrack.name }}</b>
                    <span
                        v-if="nextTrack.decoration?.label"
                        class="tv-next-tag"
                        :style="
                            nextTrack.decoration.accent ? {backgroundColor: nextTrack.decoration.accent} : undefined
                        "
                        data-test="decoration-label"
                        >{{ nextTrack.decoration.label }}</span
                    >
                    · {{ combineArtists(nextTrack.artists) }}
                    <template v-if="nextTrack.user === null && nextTrack.decoration?.displayName">
                        · {{ legacyFallbackName(nextTrack) }}
                    </template>
                </span>
                <span class="tv-time end">{{ formatClock(nextTrack.length) }}</span>
            </section>
        </div>
    </NowPlayingBackdrop>
</template>

<style scoped>
.tv {
    --u: max(0.5px, min(calc(100vw / 960), calc(100vh / 540)));
    --pad-x: max(calc(64 * var(--u)), 5vw);
    --pad-y: max(calc(40 * var(--u)), 5vh);
    --playback-progress-height: calc(6 * var(--u));
    --playback-progress-track: rgba(255, 255, 255, 0.2);
    --playback-progress-fill-color: #fff;
    min-block-size: 100vh;
    min-block-size: 100dvh;
    padding: var(--pad-y) var(--pad-x);
    display: flex;
    flex-direction: column;
    font-family: var(--mp-font-family, inherit);
    font-size: calc(16 * var(--u));
}

.tv h1,
.tv h2 {
    color: #fff;
    margin: 0;
}

.tv-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: calc(24 * var(--u));
}

.tv-title-col {
    flex: 1;
    min-inline-size: 0;
}

.tv-party-name {
    font-size: calc(20 * var(--u));
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-status {
    margin-block-start: calc(8 * var(--u));
}

.tv-status :deep(.status-pill) {
    font-size: calc(14 * var(--u));
}

.tv-status:empty {
    display: none;
}

.tv-join {
    min-block-size: max(120px, calc(76 * var(--u)));
    display: flex;
    align-items: center;
    gap: calc(14 * var(--u));
    flex: none;
}

.tv-join-text {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    font-size: calc(13 * var(--u));
    color: rgba(255, 255, 255, 0.75);
}

.tv-join-url {
    font-size: calc(13 * var(--u));
    color: rgba(255, 255, 255, 0.75);
    overflow-wrap: anywhere;
}

.tv-empty-url {
    display: block;
    margin-block-start: calc(8 * var(--u));
    font-size: calc(18 * var(--u));
}

.tv-join-code {
    font-size: calc(26 * var(--u));
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.2;
    color: #fff;
    font-variant-numeric: tabular-nums;
}

.tv-qr {
    display: block;
    flex: none;
    box-sizing: border-box;
    padding: calc(6 * var(--u));
    border-radius: calc(8 * var(--u));
    background: #fff;
    aspect-ratio: 1;
    object-fit: contain;
}

.tv-qr-small {
    inline-size: max(120px, calc(76 * var(--u)));
    block-size: max(120px, calc(76 * var(--u)));
}

.tv-qr-large {
    inline-size: clamp(220px, 40vh, 880px);
    block-size: clamp(220px, 40vh, 880px);
    padding: calc(12 * var(--u));
}

.tv-stage {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-block-size: 0;
}

.tv-now {
    display: flex;
    align-items: stretch;
    gap: calc(32 * var(--u));
    margin-block-start: calc(22 * var(--u));
    padding-inline: calc(32 * var(--u));
}

.tv-art {
    flex: none;
    display: block;
    inline-size: calc(230 * var(--u));
    block-size: calc(230 * var(--u));
    border-radius: calc(8 * var(--u));
    box-shadow: 0 calc(14 * var(--u)) calc(40 * var(--u)) rgba(0, 0, 0, 0.5);
    object-fit: cover;
}

.tv-art-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(80 * var(--u));
}

.tv-now-body {
    flex: 1;
    min-inline-size: 0;
    display: flex;
    flex-direction: column;
}

.tv-track-name {
    font-size: calc(36 * var(--u));
    font-weight: 700;
    letter-spacing: -0.015em;
    line-height: 1.15;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tv-artists,
.tv-album {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-artists {
    margin-block-start: calc(10 * var(--u));
    font-size: calc(21 * var(--u));
    color: rgba(255, 255, 255, 0.9);
}

.tv-album {
    margin-block-start: calc(4 * var(--u));
    font-size: calc(16 * var(--u));
    color: rgba(255, 255, 255, 0.62);
}

.tv-spacer {
    flex: 1;
}

.tv-by-row {
    display: flex;
    align-items: center;
    gap: calc(14 * var(--u));
}

.tv-by {
    flex: 1;
    min-inline-size: 0;
    font-size: calc(16 * var(--u));
    color: rgba(255, 255, 255, 0.78);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-by b {
    color: #fff;
    font-weight: 600;
}

.tv-score {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: calc(8 * var(--u));
    font-size: calc(18 * var(--u));
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.tv-score .ti {
    font-size: calc(22 * var(--u));
}

.tv-progress-row {
    display: flex;
    align-items: center;
    gap: calc(14 * var(--u));
    margin-block-start: calc(14 * var(--u));
}

.tv-progress {
    flex: 1;
}

.tv-time {
    min-inline-size: 3em;
    font-size: calc(14 * var(--u));
    font-variant-numeric: tabular-nums;
    color: rgba(255, 255, 255, 0.75);
}

.tv-time.end {
    text-align: end;
}

.tv-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: calc(56 * var(--u));
}

.tv-empty-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: calc(12 * var(--u));
}

.tv-empty-copy > * {
    margin: 0;
}

.tv-empty-title {
    font-size: calc(40 * var(--u));
    line-height: 1.1;
    font-weight: 700;
}

.tv-empty-copy p {
    font-size: calc(19 * var(--u));
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.78);
}

.tv-empty-code {
    display: block;
    line-height: 1.1;
    font-size: calc(40 * var(--u));
    font-weight: 700;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
}

.tv-next {
    margin-block-start: auto;
    margin-inline: calc(32 * var(--u));
    display: flex;
    align-items: center;
    gap: calc(16 * var(--u));
    padding-block-start: calc(18 * var(--u));
    border-block-start: 1px solid rgba(255, 255, 255, 0.15);
    font-size: calc(17 * var(--u));
    color: rgba(255, 255, 255, 0.78);
}

.tv-next.decorated {
    border-block-start-width: 2px;
}

.tv-next-label {
    flex: none;
    font-size: calc(14 * var(--u));
    color: rgba(255, 255, 255, 0.6);
    white-space: nowrap;
}

.tv-next-art {
    flex: none;
    inline-size: calc(56 * var(--u));
    block-size: calc(56 * var(--u));
    border-radius: calc(6 * var(--u));
    object-fit: cover;
}

.tv-next-blank {
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-size: calc(28 * var(--u));
}

.tv-next-meta {
    flex: 1;
    min-inline-size: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-next-meta b {
    color: #fff;
    font-weight: 600;
}

.tv-next-tag {
    margin-inline: calc(8 * var(--u));
    padding: calc(2 * var(--u)) calc(8 * var(--u));
    border-radius: calc(4 * var(--u));
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-size: calc(13 * var(--u));
    font-weight: 600;
}
</style>

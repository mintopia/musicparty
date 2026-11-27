<script setup lang="ts">
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlayerStatusPill from '@/components/PlayerStatusPill.vue';
import TvEmpty from '@/components/TvEmpty.vue';
import TvNextTrack from '@/components/TvNextTrack.vue';
import TvNowPlaying from '@/components/TvNowPlaying.vue';
import {useStatusProgress} from '@/composables/useProgress';
import {useWakeLock} from '@/composables/useWakeLock';
import type {LegacyTvPartyState} from '@/types';

const STALE_AFTER_MS = 60000;

const props = withDefaults(
    defineProps<{
        code: string;
        qrSvg?: string;
        state: LegacyTvPartyState;
        connected?: boolean;
    }>(),
    {qrSvg: undefined, connected: true},
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
const showQr = computed(() => props.state.show_qrcode && Boolean(props.qrSvg));
const qrCodeSrc = computed(() =>
    props.qrSvg ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(props.qrSvg)}` : null,
);
const nowPlaying = computed(() => props.state.now !== null);
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
            <TvNowPlaying
                v-if="state.now !== null"
                :state="state"
                :has-length="hasLength"
                :progress-percent="progressPercent"
                :elapsed-label="elapsedLabel"
                :total-label="totalLabel"
            />
            <TvEmpty v-else :code="code" :name="state.name" :show-qr="showQr" :qr-code-src="qrCodeSrc" />
            <TvNextTrack v-if="state.now !== null && state.next !== null" :track="state.next" />
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

.tv-stage {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-block-size: 0;
}
</style>

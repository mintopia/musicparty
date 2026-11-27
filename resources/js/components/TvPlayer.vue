<template>
    <NowPlayingBackdrop :image="backgroundImage" class="tv" data-test="tv-player">
        <header class="tv-title">
            <h1 class="tv-party-name">{{ state.name }}</h1>
        </header>

        <section class="tv-join" aria-label="Join this party">
            <div class="tv-join-label">Join with code</div>
            <div class="tv-join-code" data-test="join-code">{{ code }}</div>
            <img
                v-if="state.show_qrcode && qrCodeSrc"
                class="tv-qr"
                :src="qrCodeSrc"
                :alt="`Scan to join ${code}`"
                width="256"
                height="256"
                decoding="async"
                data-test="tv-qr"
            />
        </section>

        <section class="tv-now" aria-label="Now playing">
            <template v-if="state.now !== null">
                <img
                    class="tv-art"
                    :src="state.now.album.image_url"
                    alt=""
                    width="352"
                    height="352"
                    decoding="async"
                />
                <div class="tv-now-body">
                    <div class="tv-track-name"><strong>{{ state.now.name }}</strong></div>
                    <div class="tv-line">
                        <i class="ti ti-users"></i>
                        {{ combineArtists(state.now.artists) }}
                    </div>
                    <div class="tv-line">
                        <i class="ti ti-vinyl"></i>
                        {{ state.now.album.name }}
                    </div>
                    <div
                        v-if="state.current && state.current.provider_track_id === state.now.provider_track_id"
                        class="tv-line"
                    >
                        <i class="ti ti-music-question"></i>
                        <template v-if="state.current?.user != null">Requested by {{ state.current?.user }}</template>
                        <template v-else>Fallback Track</template>
                    </div>
                    <div class="tv-line">
                        <i class="ti ti-heart"></i>
                        {{ state.current?.rating }}
                    </div>
                    <PlaybackProgress class="tv-progress" :percent="progressPercent" />
                    <div class="d-flex justify-content-between tv-times">
                        <span>{{ currentTime }}</span>
                        <span>{{ endTime }}</span>
                    </div>
                </div>
            </template>
            <EmptyState v-else class="tv-empty" title="There is nothing playing" icon="ti-music-off" />
        </section>

        <section v-if="state.now !== null && state.next !== null" class="tv-next" aria-label="Next up">
            <h2 class="tv-next-heading">Next</h2>
            <div :class="legacyRowClasses(state.next)" :style="legacyAccentStyle(state.next)">
                <div class="col-auto">
                    <img
                        class="tv-art-small"
                        :src="state.next.album.image_url"
                        alt=""
                        width="128"
                        height="128"
                        decoding="async"
                    />
                </div>
                <div class="col tv-next-body">
                    <div class="tv-next-name">
                        <strong>{{ state.next.name }}</strong>
                        <span
                            v-if="state.next.decoration?.label"
                            class="badge bg-azure-lt ms-2"
                            data-test="decoration-label"
                            >{{ state.next.decoration.label }}</span
                        >
                    </div>
                    <div class="tv-line">
                        <i class="ti ti-users"></i>
                        {{ combineArtists(state.next.artists) }}
                    </div>
                    <div class="tv-line">
                        <i class="ti ti-vinyl"></i>
                        {{ state.next.album.name }}
                    </div>
                    <div class="tv-line">
                        <i class="ti ti-music-question"></i>
                        <template v-if="state.next.user !== null">Requested by {{ state.next.user }}</template>
                        <template v-else>{{ legacyFallbackName(state.next) }}</template>
                    </div>
                </div>
            </div>
        </section>
    </NowPlayingBackdrop>
</template>

<style scoped>
.tv {
    --tblr-heading-color: #fff;
    --playback-progress-height: 0.45em;
    font-size: clamp(14px, min(1.1vw, 1.9vh), 44px);
    min-height: 100vh;
    min-height: 100dvh;
    padding: 2em 2.5em;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
        'title'
        'now'
        'next'
        'join';
    gap: 1.5em;
    align-content: start;
}

@media (min-width: 900px) {
    .tv {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-rows: auto minmax(0, 1fr) auto;
        grid-template-areas:
            'title join'
            'now join'
            'next join';
        gap: 2em 3em;
    }
}

.tv h1,
.tv h2 {
    color: #fff;
}

.tv-title {
    grid-area: title;
}

.tv-party-name {
    margin: 0;
    font-size: 2.6em;
    line-height: 1.15;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tv-join {
    grid-area: join;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6em;
    text-align: center;
    align-self: start;
}

@media (min-width: 900px) {
    .tv-join {
        min-width: 14em;
    }
}

.tv-join-label {
    font-size: 1.2em;
    color: rgba(255, 255, 255, 0.8);
}

.tv-join-code {
    font-size: 3.6em;
    font-weight: 700;
    letter-spacing: 0.08em;
    line-height: 1;
    color: #fff;
    font-variant-numeric: tabular-nums;
}

.tv-qr {
    width: max(160px, 14em);
    height: auto;
    aspect-ratio: 1;
    border-radius: 0.5em;
    background: #fff;
}

.tv-now {
    grid-area: now;
    align-self: end;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 2em;
    min-width: 0;
}

.tv-art {
    width: clamp(10em, 22vw, 22em);
    height: auto;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 0.5em;
}

.tv-now-body {
    flex: 1 1 18em;
    min-width: 0;
    font-size: 1.5em;
}

.tv-track-name {
    font-size: 1.6em;
    line-height: 1.2;
    margin-bottom: 0.4em;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tv-line {
    padding-bottom: 0.3em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tv-line i {
    margin-right: 0.35em;
}

.tv-next-body {
    padding-left: 1em;
}

.tv-progress {
    margin-top: 0.6em;
}

.tv-times {
    font-size: 0.8em;
    font-variant-numeric: tabular-nums;
    color: rgba(255, 255, 255, 0.85);
}

.tv-empty {
    color: rgba(255, 255, 255, 0.85);
    font-size: 2em;
    margin: auto;
}

.tv-next {
    grid-area: next;
    min-width: 0;
    color: rgba(255, 255, 255, 0.85);
}

.tv-next-heading {
    font-size: 1.6em;
    margin: 0 0 0.5em;
}

.tv-art-small {
    width: clamp(5em, 9vw, 9em);
    height: auto;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 0.4em;
}

.tv-next-body {
    min-width: 0;
    font-size: 1.15em;
}

.tv-next-name {
    font-size: 1.4em;
    line-height: 1.2;
    margin-bottom: 0.3em;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

<script setup lang="ts">
import {computed} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import NowPlayingBackdrop from '@/components/NowPlayingBackdrop.vue';
import PlaybackProgress from '@/components/PlaybackProgress.vue';
import {formatClock, useProgress} from '@/composables/useProgress';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import type {LegacyArtist, LegacyTvPartyState, PlaybackState} from '@/types';

const props = defineProps<{
    code: string;
    qrSvg?: string;
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

const backgroundImage = computed(() => props.state.now?.album.image_url ?? null);

const qrCodeSrc = computed(() =>
    props.qrSvg ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(props.qrSvg)}` : null,
);

function combineArtists(artists: LegacyArtist[]): string {
    return artists.map((artist) => artist.name).join(', ');
}
</script>

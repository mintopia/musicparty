<script setup>
import {Head, router} from '@inertiajs/vue3';
import {RESYNC_EVENT} from '../../lib/realtimeResync';
import {computed, provide, onBeforeUnmount, onMounted, ref} from 'vue';
import Icon from '../../Components/Icon.vue';
import Decorations from '../../Components/Decorations.vue';
import QrCode from '../../Components/QrCode.vue';
import TrackThumb from '../../Components/TrackThumb.vue';
import {formatDuration, requesterLabel} from '../../lib/format';

defineOptions({layout: (_, page) => page});

const props = defineProps({
    party: {type: Object, required: true},
    nowPlaying: {type: Object, default: null},
    upNext: {type: Object, default: null},
    startedAt: {type: String, default: null},
    theme: {type: Object, default: null},
    enabled_mods: {type: Array, default: () => []},
});

provide('enabledMods', computed(() => props.enabled_mods));

const theme = ref(props.theme);
const themeStyle = ref(null);
const darkMode = () => document.documentElement.classList.contains('dark');
const logoUrl = computed(() => (darkMode() ? theme.value?.logo_dark_url : theme.value?.logo_url) ?? theme.value?.logo_url ?? null);
const backgroundUrl = computed(() => theme.value?.background_url ?? null);
const layoutClass = computed(() => `tv-layout-${theme.value?.tv_layout ?? 'default'}`);
const fontStacks = {
    inter: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
    system: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
    serif: "Georgia, 'Times New Roman', Times, serif",
    mono: "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace",
};
const declarations = (colours) => Object.entries(colours ?? {}).map(([key, value]) => `--color-${key.replace(/_/g, '-')}:${value};`).join('');
const applyThemeCss = () => {
    if (!theme.value) {
        return;
    }
    themeStyle.value ??= Object.assign(document.createElement('style'), {id: 'party-theme-live'});
    themeStyle.value.textContent = `:root{${declarations(theme.value.light)}${fontStacks[theme.value.font] ? `--font-sans:${fontStacks[theme.value.font]};` : ''}}.dark{${declarations(theme.value.dark)}}`;
    if (!themeStyle.value.isConnected) {
        document.head.appendChild(themeStyle.value);
    }
};

const nowPlaying = ref(props.nowPlaying);
const upNext = ref(props.upNext);
const startedAtMs = ref(props.startedAt ? Date.parse(props.startedAt) : null);
const now = ref(Date.now());
let ticker = null;

const durationMs = computed(() => nowPlaying.value?.track.duration_ms ?? 0);
const elapsedMs = computed(() => {
    if (startedAtMs.value === null || durationMs.value <= 0) {
        return 0;
    }
    return Math.min(Math.max(now.value - startedAtMs.value, 0), durationMs.value);
});
const progressPercent = computed(() => (durationMs.value > 0 ? (elapsedMs.value / durationMs.value) * 100 : 0));
const backdropUrl = computed(() => nowPlaying.value?.track.artwork_url ?? upNext.value?.track.artwork_url ?? null);
const channelName = `party.${props.party.code}`;

const resync = () => {
    router.reload({
        only: ['nowPlaying', 'upNext', 'startedAt'],
        async: true,
        onSuccess: () => {
            nowPlaying.value = props.nowPlaying;
            upNext.value = props.upNext;
            startedAtMs.value = props.startedAt ? Date.parse(props.startedAt) : null;
        },
    });
};

onMounted(() => {
    window.addEventListener(RESYNC_EVENT, resync);
    ticker =setInterval(() => {
        now.value = Date.now();
    }, 1000);
    const channel = window.Echo?.channel(channelName);
    channel?.listen('.ThemeUpdated', (payload) => {
        theme.value = payload;
        applyThemeCss();
    });
    channel?.listen('Party.QueueUpdatedEvent', (payload) => {
        if (payload.now_playing?.id !== nowPlaying.value?.id) {
            startedAtMs.value = payload.now_playing ? Date.now() : null;
        }
        nowPlaying.value = payload.now_playing;
        upNext.value = payload.up_next;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener(RESYNC_EVENT, resync);
    clearInterval(ticker);
    themeStyle.value?.remove();
    window.Echo?.leave(channelName);
});
</script>

<template>
    <Head :title="`${party.name} TV`" />
    <main :class="['relative min-h-screen overflow-hidden bg-black text-white', layoutClass]" data-testid="tv-screen">
        <div
            v-if="backgroundUrl"
            data-testid="tv-theme-background"
            aria-hidden="true"
            class="absolute inset-0 bg-cover bg-center"
            :style="{backgroundImage: `url(${backgroundUrl})`}"
        ></div>
        <div
            v-if="backdropUrl"
            data-testid="tv-backdrop"
            aria-hidden="true"
            class="absolute inset-0 scale-125 bg-cover bg-center opacity-25 blur-3xl"
            :style="{backgroundImage: `url(${backdropUrl})`}"
        ></div>
        <div v-else data-testid="tv-backdrop" aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-white/5 to-black"></div>
        <header class="relative flex items-start justify-between px-4 pt-4 text-2xl font-semibold">
            <img v-if="logoUrl" :src="logoUrl" alt="" data-testid="tv-logo" class="max-h-16 max-w-48 object-contain" />
            <span data-testid="tv-party-name">{{ party.name }}</span>
            <span data-testid="tv-party-code" class="tracking-wider">{{ party.code }}</span>
        </header>

        <div class="relative mx-auto flex w-full max-w-[940px] flex-col gap-10 pt-10">
            <section data-testid="tv-now-playing" aria-label="Now playing">
                <div v-if="nowPlaying" class="flex items-start gap-4">
                    <img
                        v-if="nowPlaying.track.artwork_url"
                        :src="nowPlaying.track.artwork_url"
                        alt=""
                        class="h-[300px] w-[300px] shrink-0 object-cover"
                    />
                    <div v-else class="h-[300px] w-[300px] shrink-0 bg-white/10" aria-hidden="true"></div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 text-2xl">
                        <h1 data-testid="tv-now-playing-title" class="mb-2 truncate text-3xl font-semibold">{{ nowPlaying.track.title }}</h1>
                        <div class="flex items-center gap-2"><Icon name="user" /><span data-testid="tv-now-playing-artist" class="truncate">{{ nowPlaying.track.artists.join(', ') }}</span></div>
                        <div v-if="nowPlaying.track.album" class="flex items-center gap-2"><Icon name="playlist" /><span class="truncate">{{ nowPlaying.track.album }}</span></div>
                        <div class="flex items-center gap-2"><Icon name="musicPlus" /><span data-testid="tv-now-playing-requester">{{ requesterLabel(nowPlaying) }}</span></div>
                        <Decorations :decorations="nowPlaying.decorations" />
                        <div class="flex items-center gap-2"><Icon name="heart" /><span data-testid="tv-now-playing-score">{{ nowPlaying.score }}</span></div>
                        <div class="mt-auto">
                            <div class="h-1 w-full bg-white/80" role="progressbar" :aria-valuenow="Math.round(progressPercent)" aria-valuemin="0" aria-valuemax="100" data-testid="tv-progress">
                                <div class="h-full bg-blue-600" :style="{width: `${progressPercent}%`, backgroundColor: 'var(--color-primary, #2563eb)'}" data-testid="tv-progress-fill"></div>
                            </div>
                            <div class="mt-1 flex justify-between tabular-nums">
                                <span data-testid="tv-elapsed">{{ formatDuration(elapsedMs) }}</span>
                                <span data-testid="tv-now-playing-duration">{{ formatDuration(durationMs) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else data-testid="tv-now-playing-empty" class="text-2xl text-white/70">Nothing is playing right now.</p>
            </section>

            <section data-testid="tv-up-next" aria-label="Next">
                <h2 class="mb-4 text-xl font-semibold">Next</h2>
                <div v-if="upNext" class="flex items-start gap-4 text-slate-400">
                    <TrackThumb :src="upNext.track.artwork_url" class="!h-[182px] !w-[182px] !rounded-none" />
                    <div class="flex min-w-0 flex-col gap-2 text-xl">
                        <h3 data-testid="tv-up-next-title" class="mb-2 truncate text-3xl font-semibold">{{ upNext.track.title }}</h3>
                        <div class="flex items-center gap-2"><Icon name="user" /><span class="truncate">{{ upNext.track.artists.join(', ') }}</span></div>
                        <div v-if="upNext.track.album" class="flex items-center gap-2"><Icon name="playlist" /><span class="truncate">{{ upNext.track.album }}</span></div>
                        <div class="flex items-center gap-2"><Icon name="musicPlus" /><span>{{ requesterLabel(upNext) }}</span></div>
                        <Decorations :decorations="upNext.decorations" />
                    </div>
                </div>
                <p v-else data-testid="tv-up-next-empty" class="text-xl text-white/50">Nothing queued yet.</p>
            </section>
        </div>

        <QrCode class="absolute z-10 bottom-5 left-5" :value="party.joinUrl" :size="100" />
    </main>
</template>

<style>
.tv-layout-compact h1 { font-size: 1.5rem; }
.tv-layout-compact h2 { font-size: 1rem; }
.tv-layout-fullscreen-art [data-testid="tv-backdrop"] { opacity: 0.6; filter: none; transform: none; }
.tv-layout-queue-focus h2 { font-size: 1.875rem; }
</style>

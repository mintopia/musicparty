<script setup>
import {Head} from '@inertiajs/vue3';
import {onBeforeUnmount, onMounted, ref} from 'vue';
import Icon from '../../Components/Icon.vue';
import QrCode from '../../Components/QrCode.vue';
import TrackThumb from '../../Components/TrackThumb.vue';
import {formatDuration, requesterLabel} from '../../lib/format';

defineOptions({layout: (_, page) => page});

const props = defineProps({
    party: {type: Object, required: true},
    nowPlaying: {type: Object, default: null},
    upNext: {type: Object, default: null},
    sequence: {type: Number, default: 0},
});

const nowPlaying = ref(props.nowPlaying);
const upNext = ref(props.upNext);
let lastSequence = props.sequence;
const channelName = `party.${props.party.code}`;

onMounted(() => {
    window.Echo?.channel(channelName).listen('Party.QueueUpdatedEvent', (payload) => {
        if (payload.sequence <= lastSequence) {
            return;
        }
        lastSequence = payload.sequence;
        nowPlaying.value = payload.now_playing;
        upNext.value = payload.up_next;
    });
});

onBeforeUnmount(() => {
    window.Echo?.leave(channelName);
});
</script>

<template>
    <Head :title="`${party.name} TV`" />
    <main class="relative min-h-screen bg-black text-white" data-testid="tv-screen">
        <header class="flex items-start justify-between px-4 pt-4 text-2xl font-semibold">
            <span data-testid="tv-party-name">{{ party.name }}</span>
            <span data-testid="tv-party-code" class="tracking-wider">{{ party.code }}</span>
        </header>

        <div class="mx-auto flex w-full max-w-[940px] flex-col gap-10 pt-10">
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
                        <div class="mt-auto flex justify-end tabular-nums" data-testid="tv-now-playing-duration">{{ formatDuration(nowPlaying.track.duration_ms) }}</div>
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
                    </div>
                </div>
                <p v-else data-testid="tv-up-next-empty" class="text-xl text-white/50">Nothing queued yet.</p>
            </section>
        </div>

        <QrCode class="absolute bottom-5 left-5" :value="party.joinUrl" :size="100" />
    </main>
</template>

<script setup>
import Icon from './Icon.vue';
import {formatDuration, requesterLabel} from '../lib/format';

defineProps({nowPlaying: {type: Object, default: null}});
</script>

<template>
    <section data-testid="now-playing" aria-label="Now playing" class="hidden border-b border-primary bg-black text-white md:block">
        <div v-if="nowPlaying" class="mx-auto flex max-w-3xl flex-row items-start gap-4 px-0 py-4">
            <img
                v-if="nowPlaying.track.artwork_url"
                :src="nowPlaying.track.artwork_url"
                alt=""
                class="h-[300px] w-[300px] shrink-0 object-cover"
            />
            <div v-else class="h-[300px] w-[300px] shrink-0 bg-border" aria-hidden="true"></div>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
                <h2 data-testid="now-playing-title" class="truncate text-xl font-semibold">{{ nowPlaying.track.title }}</h2>
                <div class="flex items-center gap-2 text-sm"><Icon name="user" /><span data-testid="now-playing-artist" class="truncate">{{ nowPlaying.track.artists.join(', ') }}</span></div>
                <div v-if="nowPlaying.track.album" class="flex items-center gap-2 text-sm"><Icon name="playlist" /><span class="truncate">{{ nowPlaying.track.album }}</span></div>
                <div class="flex items-center gap-2 text-sm"><Icon name="musicPlus" /><span data-testid="now-playing-requester">{{ requesterLabel(nowPlaying) }}</span></div>
                <div class="mt-2 flex justify-end text-sm tabular-nums">{{ formatDuration(nowPlaying.track.duration_ms) }}</div>
            </div>
        </div>
        <p v-else data-testid="now-playing-empty" class="mx-auto max-w-3xl px-0 py-8 text-sm text-white/70">Nothing is playing right now.</p>
    </section>
</template>

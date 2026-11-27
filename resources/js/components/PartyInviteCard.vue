<script setup lang="ts">
import type {PlaybackState} from '@/components/StatePill.vue';

export interface PartyInvite {
    code: string;
    name: string;
    host: string;
    guests: number;
    playback: PlaybackState;
    detail: string | null;
    cover: string | null;
}

defineProps<{party: PartyInvite}>();

const dots: Partial<Record<PlaybackState, string>> = {
    playing: 'status-green',
    paused: 'status-yellow',
    stale: 'status-red',
};
const labels: Partial<Record<PlaybackState, string>> = {playing: 'Playing', paused: 'Paused', stale: 'Player stale'};
</script>

<template>
    <div class="mp-invite" data-test="party-invite">
        <span
            class="avatar avatar-lg rounded mp-party-cover"
            :style="party.cover ? {backgroundImage: `url('${party.cover}')`} : undefined"
            role="img"
            aria-label="Cover art"
        ></span>
        <div>
            <h1 class="h3 mb-1">{{ party.name }}</h1>
            <p class="text-secondary">
                Hosted by {{ party.host }}, {{ party.guests }} {{ party.guests === 1 ? 'guest' : 'guests' }}
            </p>
            <p class="small">
                <span class="status-dot" :class="dots[party.playback] ?? 'status-secondary'"></span
                >{{ labels[party.playback] ?? 'Nothing playing'
                }}<template v-if="party.detail"> {{ party.detail }}</template>
            </p>
        </div>
    </div>
</template>

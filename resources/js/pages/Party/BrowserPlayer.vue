<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {computed} from 'vue';
import {useBrowserPlayer} from '../../composables/useBrowserPlayer';

const props = defineProps({
    party: {type: Object, required: true},
    accessToken: {type: String, default: null},
    error: {type: String, default: null},
    leadSeconds: {type: Number, required: true},
    channel: {type: String, required: true},
});

const player = useBrowserPlayer({partyCode: props.party.code, accessToken: props.accessToken, channel: props.channel});
const canStart = computed(() => props.accessToken !== null && ['idle', 'blocked', 'error'].includes(player.status.value));
const progress = computed(() => (player.durationMs.value > 0 ? Math.min(100, (player.positionMs.value / player.durationMs.value) * 100) : 0));
</script>

<template>
    <Head :title="`Player - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Browser player</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <p v-if="error" role="alert" data-testid="player-error" class="rounded border border-border bg-surface px-5 py-3 text-sm">{{ error }}</p>

        <section v-else class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5">
            <p data-testid="player-status" class="text-sm text-muted">
                <template v-if="player.status.value === 'idle'">Start the player to make this tab the Party's speaker.</template>
                <template v-else-if="player.status.value === 'connecting'">Connecting...</template>
                <template v-else-if="player.status.value === 'blocked'">{{ player.message.value }}</template>
                <template v-else-if="player.status.value === 'error'">{{ player.message.value }}</template>
                <template v-else-if="player.status.value === 'playing'">Playing</template>
                <template v-else>Ready. The next track is handed over about {{ leadSeconds }} seconds before the current one ends.</template>
            </p>
            <div v-if="player.track.value" data-testid="player-track">
                <p class="font-medium">{{ player.track.value.name }}</p>
                <p class="text-sm text-muted">{{ player.track.value.artists?.map((artist) => artist.name).join(', ') }}</p>
                <div class="mt-2 h-1 rounded bg-border"><div class="h-1 rounded bg-primary" :style="{width: `${progress}%`}" /></div>
            </div>
            <button
                v-if="canStart"
                type="button"
                data-testid="player-start"
                class="min-h-11 self-start rounded border border-border px-4 text-sm hover:text-primary"
                @click="player.start()"
            >Start player</button>
        </section>
    </div>
</template>

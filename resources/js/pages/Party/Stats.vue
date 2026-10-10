<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    stats: {type: Object, required: true},
});

const live = ref(props.stats);
const channelName = `party.${props.party.code}.members`;

onMounted(() => {
    window.Echo?.join(channelName).listen('.stats.updated', (payload) => {
        live.value = payload;
    });
});

onBeforeUnmount(() => {
    window.Echo?.leave(channelName);
});

const minutes = computed(() => Math.floor((live.value.total_time_played_ms ?? 0) / 60000));
const timePlayed = computed(() => {
    const hours = Math.floor(minutes.value / 60);
    return hours > 0 ? `${hours}h ${minutes.value % 60}m` : `${minutes.value}m`;
});
const artists = (item) => (item.artists ?? []).join(', ');

const sections = computed(() => [
    {key: 'top-tracks', title: 'Top tracks', rows: live.value.top_tracks, empty: 'Nothing has played yet.', label: (row) => `${row.title} - ${artists(row)}`, value: (row) => `${row.plays} plays`},
    {key: 'top-requesters', title: 'Top requesters', rows: live.value.top_requesters, empty: 'No requests have played yet.', label: (row) => row.nickname, value: (row) => `${row.plays} plays`},
    {key: 'most-upvoted', title: 'Most upvoted', rows: live.value.most_upvoted, empty: 'No upvoted requests yet.', label: (row) => `${row.title} - ${artists(row)}`, value: (row) => `+${row.score}`},
    {key: 'most-downvoted', title: 'Most downvoted', rows: live.value.most_downvoted, empty: 'No downvoted requests yet.', label: (row) => `${row.title} - ${artists(row)}`, value: (row) => `${row.score}`},
    {key: 'upvote-leaderboard', title: 'Most upvoted members', rows: live.value.upvote_leaderboard ?? [], empty: 'No upvotes received yet.', label: (row) => row.nickname, value: (row) => `${row.votes} upvotes`},
    {key: 'downvote-leaderboard', title: 'Most downvoted members', rows: live.value.downvote_leaderboard ?? [], empty: 'No downvotes received yet.', label: (row) => row.nickname, value: (row) => `${row.votes} downvotes`},
]);
</script>

<template>
    <Head :title="`Stats - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Stats</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <section class="rounded border border-border bg-surface px-5 py-5">
            <h2 class="text-sm text-muted">Total time played</h2>
            <p data-testid="stats-time-played" class="text-2xl font-semibold">{{ timePlayed }}</p>
        </section>

        <section v-for="section in sections" :key="section.key" class="rounded border border-border bg-surface px-5 py-5">
            <h2 class="mb-2 text-sm font-medium">{{ section.title }}</h2>
            <p v-if="section.rows.length === 0" :data-testid="`stats-${section.key}-empty`" class="text-sm text-muted">{{ section.empty }}</p>
            <ol v-else class="flex flex-col divide-y divide-border">
                <li v-for="(row, index) in section.rows" :key="index" :data-testid="`stats-${section.key}-row`" class="flex justify-between gap-3 py-2 text-sm">
                    <span class="truncate">{{ section.label(row) }}</span>
                    <span class="text-muted">{{ section.value(row) }}</span>
                </li>
            </ol>
        </section>
    </div>
</template>

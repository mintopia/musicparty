<script setup lang="ts">
import {VueEternalLoading} from '@ts-pro/vue-eternal-loading';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import axios from 'axios';
import {onBeforeUnmount, ref, watch} from 'vue';
import {useEcho} from '@/composables/useEcho';
import EmptyState from '@/components/EmptyState.vue';
import Toast from '@/components/Toast.vue';
import TrackRow from '@/components/TrackRow.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';
import {useIsPhone} from '@/composables/useIsPhone';
import {legacyFallbackLabel, legacyToAnnotation} from '@/lib/legacyTrack';
import {destroy, index} from '@/routes/api/v1/parties/upcomingsongs';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {LegacyUpcomingSong} from '@/types';

const props = withDefaults(
    defineProps<{
        party: string;
        canManage?: boolean;
        canDownvote?: boolean;
        timezone?: string;
        initialState: LegacyUpcomingSong[] | null;
    }>(),
    {canManage: false, canDownvote: false, timezone: 'UTC'},
);

const emit = defineEmits<{count: [total: number]}>();

const isPhone = useIsPhone();
const page = ref(1);
const upcoming = ref<LegacyUpcomingSong[]>([]);
const message = ref('');
let messageTimer: ReturnType<typeof setTimeout> | null = null;

function showMessage(text: string): void {
    message.value = text;
    if (messageTimer !== null) {
        clearTimeout(messageTimer);
    }
    messageTimer = setTimeout(() => {
        message.value = '';
        messageTimer = null;
    }, 5000);
}

function formatScheduled(iso: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: props.timezone,
    }).format(new Date(iso));
}

function sortUpcoming(): void {
    upcoming.value.sort((alpha, bravo) => {
        if (alpha.score !== bravo.score) {
            return Math.sign(bravo.score - alpha.score);
        }
        const diff = Math.sign(Date.parse(alpha.created_at) - Date.parse(bravo.created_at));

        return diff === 0 ? Math.sign(alpha.id - bravo.id) : diff;
    });
}

function addOrUpdateItem(newItem: LegacyUpcomingSong, fromPubSub = false): void {
    const position = upcoming.value.findIndex((existing) => existing.id === newItem.id);
    if (position === -1) {
        upcoming.value.push(newItem);
        return;
    }
    if (fromPubSub) {
        newItem.vote = upcoming.value[position].vote;
    }
    upcoming.value.splice(position, 1, newItem);
}

function removeItem(id: number): void {
    upcoming.value = upcoming.value.filter((song) => song.id !== id);
}

async function vote(songId: number, value: number): Promise<void> {
    const route = storeVote({party: props.party, upcomingsong: songId});
    try {
        const response = await axios.request({url: route.url, method: route.method, data: {vote: value}});
        addOrUpdateItem(response.data.data);
        sortUpcoming();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 400) {
            showMessage((error.response.data as {message: string}).message);
        }
    }
}

function deleteSong(songId: number): void {
    removeItem(songId);
    const route = destroy({party: props.party, upcomingsong: songId});
    void axios.request({url: route.url, method: route.method});
}

async function fetchPage(number: number): Promise<LegacyUpcomingSong[]> {
    const response = await axios.get(index(props.party).url, {params: {page: number}});

    return response.data.data as LegacyUpcomingSong[];
}

async function getPage({loaded, error}: LoadAction): Promise<void> {
    try {
        const items = await fetchPage(page.value);
        if (items.length > 0) {
            page.value += 1;
            items.forEach((item) => addOrUpdateItem(item));
            sortUpcoming();
        }
        loaded(items.length, 20);
    } catch {
        error();
    }
}

async function resync(): Promise<void> {
    const last = Math.max(page.value - 1, 1);
    try {
        const fresh: LegacyUpcomingSong[] = [];
        for (let number = 1; number <= last; number += 1) {
            fresh.push(...(await fetchPage(number)));
        }
        const votes = new Map(upcoming.value.map((song) => [song.id, song.vote]));
        upcoming.value = fresh.map((song) => ({...song, vote: votes.get(song.id) ?? song.vote}));
        sortUpcoming();
    } catch {
        showMessage('Could not refresh the queue.');
    }
}

(props.initialState ?? []).forEach((item) => addOrUpdateItem(item));

const echo = useEcho();
const channel = echo.privateChannel(`party.${props.party}`) as {
    listen<T>(event: string, cb: (payload: T) => void): unknown;
};
channel.listen<LegacyUpcomingSong>('UpcomingSong\\UpdatedEvent', (payload) => {
    addOrUpdateItem(payload, true);
    sortUpcoming();
});
channel.listen<{id: number}>('UpcomingSong\\RemovedEvent', (payload) => {
    removeItem(payload.id);
});
echo.onReconnect(() => void resync());

watch(
    () => upcoming.value.length,
    (total) => emit('count', total),
    {immediate: true},
);

onBeforeUnmount(() => {
    if (messageTimer !== null) {
        clearTimeout(messageTimer);
    }
});
</script>
<template>
    <div>
        <div v-if="upcoming.length > 0" class="card" data-test="queue-card">
            <TransitionGroup name="queue" tag="div" class="list-group list-group-flush">
                <TrackRow
                    v-for="song in upcoming"
                    :key="song.id"
                    :track="legacyToAnnotation(song)"
                    :can-downvote="canDownvote"
                    :compact="isPhone"
                    :removable="canManage"
                    :scheduled-label="song.scheduled_for ? formatScheduled(song.scheduled_for) : undefined"
                    :fallback-name="legacyFallbackLabel(song)"
                    @vote="(_track, value) => vote(song.id, value)"
                    @remove="deleteSong(song.id)"
                />
            </TransitionGroup>
        </div>
        <VueEternalLoading :load="getPage">
            <template #loading>
                <div class="card" aria-hidden="true">
                    <div class="list-group list-group-flush">
                        <TrackRowPlaceholder v-for="n in 3" :key="n" :compact="isPhone" />
                    </div>
                </div>
            </template>

            <template #no-results>
                <div class="card">
                    <EmptyState
                        icon="ti-playlist-off"
                        title="The queue is empty"
                        description="Search for a track to add one."
                    />
                </div>
            </template>

            <template #no-more><span /></template>

            <template #error>
                <div class="text-center text-secondary p-2">Error fetching upcoming songs</div>
            </template>
        </VueEternalLoading>
        <Toast :message="message" @dismiss="message = ''" />
    </div>
</template>

<style scoped>
.queue-move,
.queue-enter-active,
.queue-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.queue-enter-from,
.queue-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

@media (prefers-reduced-motion: reduce) {
    .queue-move,
    .queue-enter-active,
    .queue-leave-active {
        transition: none;
    }
}
</style>

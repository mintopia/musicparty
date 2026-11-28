<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import axios from 'axios';
import {computed, onBeforeUnmount, ref} from 'vue';
import ScheduleSheet from '@/components/Dashboard/ScheduleSheet.vue';
import {useEcho} from '@/composables/useEcho';
import QueueEmpty from '@/components/Upcoming/QueueEmpty.vue';
import QueueLoader from '@/components/Upcoming/QueueLoader.vue';
import QueueRow from '@/components/Upcoming/QueueRow.vue';
import Toast from '@/components/Toast.vue';
import {destroy, index} from '@/routes/api/v1/parties/upcomingsongs';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {LegacyUpcomingSong, SharedProps} from '@/types';

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

const currentUserId = usePage<SharedProps>().props.auth?.user?.id ?? null;
const isMine = (song: LegacyUpcomingSong): boolean => currentUserId !== null && song.user_id === currentUserId;

const PAGE_SIZE = 20;
const initialCount = props.initialState?.length ?? null;
const listComplete = ref(initialCount !== null && initialCount < PAGE_SIZE);
const page = ref(initialCount === PAGE_SIZE ? 2 : 1);
const upcoming = ref<LegacyUpcomingSong[]>([]);
const UNDO_WINDOW_MS = 8000;

type Notice = {kind: 'error' | 'undo' | 'failed'; text: string; name?: string; id?: number};

const notice = ref<Notice | null>(null);
const failedIds = ref(new Set<number>());
const openMenuId = ref<number | null>(null);
const scheduling = ref<LegacyUpcomingSong | null>(null);
const scheduleAnchor = ref<{bottom: number; right: number} | null>(null);
const message = computed(() => notice.value?.text ?? '');
let messageTimer: ReturnType<typeof setTimeout> | null = null;
let pending: {song: LegacyUpcomingSong; timer: ReturnType<typeof setTimeout> | null} | null = null;

function showMessage(text: string): void {
    notice.value = {kind: 'error', text};
    if (messageTimer !== null) {
        clearTimeout(messageTimer);
    }
    messageTimer = setTimeout(() => {
        notice.value = null;
        messageTimer = null;
    }, 5000);
}

function clearNoticeTimer(): void {
    if (messageTimer !== null) {
        clearTimeout(messageTimer);
        messageTimer = null;
    }
}

function openSchedule(song: LegacyUpcomingSong, event?: MouseEvent): void {
    openMenuId.value = null;
    const rect = (event?.currentTarget as HTMLElement | null)?.getBoundingClientRect();
    scheduleAnchor.value = rect ? {bottom: rect.bottom, right: rect.right} : null;
    scheduling.value = song;
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

function startRemoval(song: LegacyUpcomingSong): void {
    void commitPending();
    openMenuId.value = null;
    failedIds.value.delete(song.id);
    removeItem(song.id);
    clearNoticeTimer();
    notice.value = {kind: 'undo', text: `Removed ${song.name} from the Queue.`, name: song.name, id: song.id};
    pending = {song, timer: null};
    armUndoTimer();
}

function armUndoTimer(): void {
    if (pending === null) {
        return;
    }
    if (pending.timer !== null) {
        clearTimeout(pending.timer);
    }
    pending.timer = setTimeout(() => void commitPending(), UNDO_WINDOW_MS);
}

function pauseUndoTimer(): void {
    if (pending?.timer != null) {
        clearTimeout(pending.timer);
        pending.timer = null;
    }
}

function undoRemoval(): void {
    if (pending === null) {
        return;
    }
    const {song, timer} = pending;
    if (timer !== null) {
        clearTimeout(timer);
    }
    pending = null;
    notice.value = null;
    addOrUpdateItem(song);
    sortUpcoming();
}

async function commitPending(): Promise<void> {
    if (pending === null) {
        return;
    }
    const {song, timer} = pending;
    if (timer !== null) {
        clearTimeout(timer);
    }
    pending = null;
    if (notice.value?.kind === 'undo') {
        notice.value = null;
    }
    const route = destroy({party: props.party, upcomingsong: song.id});
    try {
        await axios.request({url: route.url, method: route.method});
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            return;
        }
        failedIds.value.add(song.id);
        addOrUpdateItem(song);
        sortUpcoming();
        clearNoticeTimer();
        notice.value = {
            kind: 'failed',
            text: `Couldn't remove ${song.name}. It is back in the Queue.`,
            name: song.name,
            id: song.id,
        };
    }
}

function retryRemoval(): void {
    const song = upcoming.value.find((candidate) => candidate.id === notice.value?.id);
    if (song !== undefined) {
        startRemoval(song);
        void commitPending();
    }
}

function onNoticeAction(): void {
    if (notice.value?.kind === 'undo') {
        undoRemoval();
    } else if (notice.value?.kind === 'failed') {
        retryRemoval();
    }
}

function dismissNotice(): void {
    if (notice.value?.kind === 'undo') {
        void commitPending();
    }
    notice.value = null;
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
        loaded(items.length, PAGE_SIZE);
    } catch {
        error();
    }
}

async function resync(): Promise<void> {
    try {
        (await fetchPage(1)).forEach((item) => addOrUpdateItem(item));
        sortUpcoming();
    } catch {
        showMessage('Could not refresh the queue.');
    }
}

(props.initialState ?? []).forEach((item) => addOrUpdateItem(item));

function closeMenu(): void {
    openMenuId.value = null;
}

document.addEventListener('click', closeMenu);
onBeforeUnmount(() => document.removeEventListener('click', closeMenu));

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

onBeforeUnmount(() => {
    clearNoticeTimer();
    void commitPending();
});
</script>
<template>
    <div>
        <div v-if="upcoming.length > 0" class="card queue-card" data-test="queue-card">
            <TransitionGroup name="queue" tag="div" class="list-group list-group-flush">
                <QueueRow
                    v-for="(song, index) in upcoming"
                    :key="song.id"
                    :song="song"
                    :position="index + 1"
                    :party="party"
                    :timezone="timezone"
                    :can-manage="canManage"
                    :can-downvote="canDownvote"
                    :is-mine="isMine(song)"
                    :failed="failedIds.has(song.id)"
                    :menu-open="openMenuId === song.id"
                    @vote="(value) => vote(song.id, value)"
                    @schedule="(event) => openSchedule(song, event)"
                    @remove="startRemoval(song)"
                    @toggle-menu="(id) => (openMenuId = openMenuId === id ? null : id)"
                />
            </TransitionGroup>
        </div>
        <div v-if="listComplete && upcoming.length === 0" class="card" data-test="queue-empty">
            <QueueEmpty />
        </div>
        <QueueLoader v-if="!listComplete" :load="getPage" />
        <ScheduleSheet
            v-if="scheduling"
            :key="scheduling.id"
            :title="`Schedule ${scheduling.name}`"
            :party="party"
            :song="scheduling.id"
            :timezone="timezone"
            :scheduled-for="scheduling.scheduled_for"
            :anchor="scheduleAnchor"
            @saved="resync"
            @close="scheduling = null"
        />
        <Toast
            :message="message"
            :variant="notice?.kind === 'undo' ? 'dark' : 'danger'"
            :action-label="notice?.kind === 'undo' ? 'Undo' : notice?.kind === 'failed' ? 'Retry' : undefined"
            data-test="queue-toast"
            @action="onNoticeAction"
            @dismiss="dismissNotice"
            @focusin="pauseUndoTimer"
            @focusout="armUndoTimer"
        >
            <template v-if="notice?.name">
                {{ notice.kind === 'undo' ? 'Removed' : "Couldn't remove" }} <b>{{ notice.name }}</b
                >{{ notice.kind === 'undo' ? ' from the Queue.' : '. It is back in the Queue.' }}
            </template>
            <template v-else>{{ message }}</template>
        </Toast>
    </div>
</template>

<style scoped>
.queue-card {
    border: 0;
    box-shadow: none;
    background: transparent;
}

.list-group-flush > .mp-qrow {
    border: 0;
    border-top: 1px solid var(--tblr-border-color);
    border-radius: 0;
    background: transparent;
}

.list-group-flush > .mp-qrow:first-child {
    border-top: 0;
}

.list-group-flush > .mp-qrow.is-mine {
    background: color-mix(in oklab, var(--tblr-primary) 6%, transparent);
    border-radius: 10px;
    border-color: transparent;
}

.list-group-flush > .mp-qrow.is-mine + .mp-qrow {
    border-top-color: transparent;
}

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

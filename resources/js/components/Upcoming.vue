<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {VueEternalLoading} from '@ts-pro/vue-eternal-loading';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import axios from 'axios';
import {computed, onBeforeUnmount, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import EmptyState from '@/components/EmptyState.vue';
import Toast from '@/components/Toast.vue';
import VoteControl from '@/components/VoteControl.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';
import {formatMs} from '@/lib/dashboard';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import {destroy, index} from '@/routes/api/v1/parties/upcomingsongs';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {LegacyArtist, LegacyUpcomingSong, SharedProps} from '@/types';

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

const customFallbackName = (song: LegacyUpcomingSong): string | null => {
    const name = legacyFallbackName(song);

    return name === null || name === 'Fallback Track' ? null : name;
};

const focusSearch = (): void => document.getElementById('query')?.focus();

const page = ref(1);
const upcoming = ref<LegacyUpcomingSong[]>([]);
const UNDO_WINDOW_MS = 8000;

type Notice = {kind: 'error' | 'undo' | 'failed'; text: string; name?: string; id?: number};

const notice = ref<Notice | null>(null);
const failedIds = ref(new Set<number>());
const openMenuId = ref<number | null>(null);
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

function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
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
                <div
                    v-for="(song, index) in upcoming"
                    :key="song.id"
                    class="list-group-item mp-qrow"
                    :class="[
                        {'is-mine': isMine(song), 'mp-failed': failedIds.has(song.id)},
                        ...legacyRowClasses(song)
                            .split(' ')
                            .filter((c) => !['row', 'g-2', 'align-items-center'].includes(c)),
                    ]"
                    :style="legacyAccentStyle(song)"
                    data-test="track-row"
                >
                    <div class="q-pos" aria-hidden="true">{{ index + 1 }}</div>
                    <div class="q-art">
                        <img
                            :src="song.album.image_url"
                            :title="song.album.name"
                            :alt="song.album.name"
                            class="rounded"
                            width="48"
                            height="48"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                    <div class="q-main">
                        <div class="q-title">
                            <span>{{ song.name }}</span>
                            <span v-if="isMine(song)" class="badge bg-blue-lt" data-test="your-request">You</span>
                            <span
                                v-if="song.decoration?.label"
                                class="badge bg-azure-lt"
                                data-test="decoration-label"
                                >{{ song.decoration.label }}</span
                            >
                        </div>
                        <div class="q-by">
                            {{ combineArtists(song.artists) }} &middot;
                            <template v-if="isMine(song)">Requested by <b>you</b></template>
                            <template v-else-if="song.user"
                                >Requested by <b>{{ song.user }}</b></template
                            >
                            <template v-else-if="customFallbackName(song)">{{ customFallbackName(song) }}</template>
                            <span v-else class="mp-credit" data-test="fallback-credit">
                                <i class="icon ti ti-playlist"></i>From the party&rsquo;s Fallback Playlist
                            </span>
                            <span class="q-length-inline"> &middot; {{ formatMs(song.length) }}</span>
                        </div>
                        <div v-if="song.scheduled_for" class="text-info small" data-test="scheduled-time">
                            <i class="icon ti ti-clock"></i>
                            Scheduled for {{ formatScheduled(song.scheduled_for) }}
                        </div>
                    </div>
                    <div class="q-len text-secondary">{{ formatMs(song.length) }}</div>
                    <div class="q-votes">
                        <VoteControl
                            :score="song.score"
                            :vote="song.vote"
                            :can-downvote="canDownvote"
                            :label="song.name"
                            @vote="(value) => vote(song.id, value)"
                        />
                    </div>
                    <div v-if="canManage" class="q-manage">
                        <button
                            type="button"
                            class="btn btn-icon btn-ghost-secondary rm"
                            :aria-label="`Remove ${song.name}`"
                            title="Remove from Queue"
                            data-test="remove"
                            @click="startRemoval(song)"
                        >
                            <i class="icon ti ti-trash"></i>
                        </button>
                    </div>
                    <div v-if="canManage" class="q-menu" @click.stop>
                        <button
                            type="button"
                            class="btn btn-icon btn-ghost-secondary"
                            :aria-label="`Actions for ${song.name}`"
                            aria-haspopup="true"
                            :aria-expanded="openMenuId === song.id"
                            data-test="actions"
                            @click="openMenuId = openMenuId === song.id ? null : song.id"
                        >
                            <i class="icon ti ti-dots-vertical"></i>
                        </button>
                        <div v-if="openMenuId === song.id" class="dropdown-menu show" data-test="actions-menu">
                            <button
                                type="button"
                                class="dropdown-item text-danger"
                                data-test="remove-menu"
                                @click="startRemoval(song)"
                            >
                                <i class="icon ti ti-trash me-2"></i>
                                <span class="q-menu-text">
                                    <span>Remove from Queue</span>
                                    <small class="text-secondary">Guests can request it again</small>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
        <VueEternalLoading :load="getPage">
            <template #loading>
                <div class="card" aria-hidden="true">
                    <div class="list-group list-group-flush">
                        <TrackRowPlaceholder v-for="n in 3" :key="n" />
                    </div>
                </div>
            </template>

            <template #no-results>
                <div class="card">
                    <EmptyState
                        icon="ti-playlist-off"
                        title="The Queue is empty"
                        description="The party is waiting on you. Search for a track to add the first one."
                    >
                        <button type="button" class="btn btn-primary" data-test="empty-search" @click="focusSearch">
                            <i class="icon ti ti-music-search me-1"></i>Search for a track
                        </button>
                    </EmptyState>
                </div>
            </template>

            <template #no-more><span /></template>

            <template #error="{retry}">
                <div class="card" role="alert" data-test="queue-error">
                    <div class="card-body text-center py-5">
                        <i class="icon ti ti-cloud-off text-secondary fs-1"></i>
                        <h2 class="h3 mt-3 mb-1">The Queue didn&rsquo;t load</h2>
                        <div class="text-secondary mb-3">
                            Check your connection, then try again. Your votes are saved.
                        </div>
                        <button type="button" class="btn btn-primary" data-test="retry" @click="retry">
                            <i class="icon ti ti-refresh me-1"></i>Retry
                        </button>
                    </div>
                </div>
            </template>
        </VueEternalLoading>
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
.mp-qrow {
    display: grid;
    grid-template-columns: 2ch 48px minmax(0, 1fr) auto auto auto;
    grid-template-areas: 'pos art main len votes manage';
    align-items: center;
    gap: 8px 14px;
    padding: 10px 12px;
}

.q-pos {
    grid-area: pos;
    text-align: end;
    color: var(--tblr-secondary-color);
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
}

.q-art {
    grid-area: art;
}

.q-art img {
    inline-size: 48px;
    block-size: 48px;
    object-fit: cover;
    border-radius: 8px;
}

.q-main {
    grid-area: main;
    min-width: 0;
    overflow-wrap: anywhere;
}

.q-title {
    font-weight: 600;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.q-by {
    font-size: 0.875rem;
    color: var(--tblr-secondary-color);
}

.q-by b {
    color: var(--tblr-body-color);
    font-weight: 600;
}

.mp-credit {
    display: inline-flex;
    align-items: center;
    gap: 0.35em;
}

.q-length-inline {
    display: none;
}

.q-len {
    grid-area: len;
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
}

.q-votes {
    grid-area: votes;
}

.q-votes :deep(.vote-control) {
    background: var(--tblr-bg-surface-secondary);
    border-radius: 99px;
    padding: 2px 4px;
}

.q-votes :deep(.vote-button) {
    min-width: 44px;
    min-height: 44px;
}

.q-votes :deep(.vote-button .icon) {
    font-size: 1.375rem;
}

.q-votes :deep(.vote-score) {
    font-weight: 600;
    min-width: 2ch;
}

.q-manage {
    grid-area: manage;
    align-self: stretch;
    display: flex;
    align-items: center;
    margin-inline-start: 4px;
    padding-inline-start: 16px;
    border-inline-start: 1px solid var(--tblr-border-color);
}

.q-manage .btn,
.q-menu .btn {
    min-width: 44px;
    min-height: 44px;
}

.q-manage .btn .icon {
    font-size: 1.25rem;
}

.q-manage .btn.rm:hover {
    color: var(--tblr-danger);
    background: color-mix(in oklab, var(--tblr-danger) 10%, transparent);
}

.q-menu {
    grid-area: menu;
    display: none;
    position: relative;
    align-self: start;
}

.q-menu .dropdown-menu {
    inset: 100% 0 auto auto;
    min-width: 15rem;
    z-index: 5;
}

.q-menu .dropdown-item {
    display: flex;
    align-items: flex-start;
    min-height: 44px;
}

.q-menu-text {
    display: flex;
    flex-direction: column;
}

.mp-qrow.mp-failed {
    box-shadow: inset 4px 0 0 var(--tblr-danger);
}

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

@media (max-width: 575px) {
    .mp-qrow {
        grid-template-columns: 48px minmax(0, 1fr) auto;
        grid-template-areas:
            'art main menu'
            'art votes votes';
        gap: 4px 12px;
        padding: 12px 10px;
    }

    .q-pos,
    .q-len,
    .q-manage {
        display: none;
    }

    .q-menu {
        display: block;
    }

    .q-length-inline {
        display: inline;
    }

    .q-votes {
        justify-self: start;
        margin-inline-start: -4px;
    }

    .q-votes :deep(.vote-control) {
        inline-size: fit-content;
        justify-content: flex-start;
    }
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

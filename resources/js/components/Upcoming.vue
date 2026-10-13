<script setup lang="ts">
import {VueEternalLoading} from '@ts-pro/vue-eternal-loading';
import type {LoadAction} from '@ts-pro/vue-eternal-loading';
import axios from 'axios';
import {onBeforeUnmount, ref} from 'vue';
import {useEcho} from '@/composables/useEcho';
import {legacyAccentStyle, legacyFallbackName, legacyRowClasses} from '@/lib/decoration';
import {destroy, index} from '@/routes/api/v1/parties/upcomingsongs';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {LegacyArtist, LegacyUpcomingSong} from '@/types';

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

function formatMs(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);

    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
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

onBeforeUnmount(() => {
    if (messageTimer !== null) {
        clearTimeout(messageTimer);
    }
});
</script>
<template>
    <div>
        <div v-if="upcoming.length > 0" class="card">
            <div class="list-group card-list-group">
                <TransitionGroup name="list" tag="div">
                    <div class="list-group-item" v-for="song in upcoming" :key="song.id">
                        <div :class="legacyRowClasses(song)" :style="legacyAccentStyle(song)">
                            <div class="col-auto">
                                <img
                                    :src="song.album.image_url"
                                    :title="song.album.name"
                                    class="rounded"
                                    width="60"
                                    height="60"
                                />
                            </div>
                            <div class="col">
                                {{ song.name }}
                                <span
                                    v-if="song.decoration?.label"
                                    class="badge bg-azure-lt ms-1"
                                    data-test="decoration-label"
                                    >{{ song.decoration.label }}</span
                                >
                                <div class="text-secondary">
                                    {{ combineArtists(song.artists) }}
                                </div>
                                <div v-if="song.scheduled_for" class="text-info scheduled-time">
                                    <i class="icon ti ti-clock"></i>
                                    Scheduled for {{ formatScheduled(song.scheduled_for) }}
                                </div>
                                <div class="text-muted">
                                    <template v-if="song.user">Requested by {{ song.user }}</template>
                                    <template v-else>{{ legacyFallbackName(song) }}</template>
                                </div>
                            </div>
                            <div class="col-auto text-secondary song-length">{{ formatMs(song.length) }}</div>
                            <div class="col-auto text-center">
                                <div class="row">
                                    <template v-if="canDownvote">
                                        <div class="col-3">
                                            <i
                                                v-if="song.vote <= 0"
                                                class="icon ti ti-arrow-big-up cursor-pointer"
                                                @click="vote(song.id, 1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === 1"
                                                class="icon ti ti-arrow-big-up-filled cursor-pointer text-success"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                        <div class="col-6">
                                            {{ song.score }}
                                        </div>
                                        <div class="col-3">
                                            <i
                                                v-if="song.vote >= 0"
                                                class="icon ti ti-arrow-big-down cursor-pointer"
                                                @click="vote(song.id, -1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === -1"
                                                class="icon ti ti-arrow-big-down-filled cursor-pointer text-danger"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="col-4">
                                            <i
                                                v-if="song.vote <= 0"
                                                class="icon ti ti-heart cursor-pointer"
                                                @click="vote(song.id, 1)"
                                            ></i>
                                            <i
                                                v-if="song.vote === 1"
                                                class="icon ti ti-heart-filled cursor-pointer text-success"
                                                @click="vote(song.id, 0)"
                                            ></i>
                                        </div>
                                        <div class="col-8">
                                            {{ song.score }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div v-if="canManage" class="col-auto lh-1">
                                <i class="icon ti ti-x cursor-pointer" @click="deleteSong(song.id)"></i>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>
        <VueEternalLoading :load="getPage">
            <template #loading>
                <div class="text-center text-muted p-2">Loading upcoming songs...</div>
            </template>

            <template #no-results>
                <div class="text-center text-muted p-2">There are no upcoming songs</div>
            </template>

            <template #no-more>
                <div class="text-center text-muted p-2">There are no more upcoming songs</div>
            </template>

            <template #error>
                <div class="text-center text-muted p-2">Error fetching upcoming songs</div>
            </template>
        </VueEternalLoading>
    </div>
    <div v-if="message" class="position-fixed z-3 p-2 top-50 start-50 translate-middle fade show">
        <div class="alert alert-important alert-danger" role="alert">
            <div class="d-flex">
                <div>
                    <i class="icon ti ti-exclamation-circle"></i>
                </div>
                <div class="px-2">
                    {{ message }}
                </div>
            </div>
        </div>
    </div>
</template>
<style>
.list-move,
.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

.list-leave-active {
    position: absolute;
}

.song-length {
    padding-right: 1rem;
}

.whamageddon {
    padding-top: 20px;
}

.whamageddon::before {
    content: 'Whamageddon!';
    white-space: nowrap;
    position: absolute;
    padding: 5px 100px;
    width: 100%;
    color: white;
    text-align: center;
    text-transform: uppercase;
    font-size: 10px;
    font-weight: bold;
    box-sizing: border-box;
    top: 0;
    left: 0;
    background-color: #990000;
}
</style>

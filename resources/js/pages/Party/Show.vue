<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, provide, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {RESYNC_EVENT} from '../../lib/realtimeResync';
import {estimateQueue} from '../../lib/queueEta';
import {formatTotal} from '../../lib/format';
import Icon from '../../Components/Icon.vue';
import UpNextCard from '../../Components/UpNextCard.vue';
import PlayedHistory from '../../Components/PlayedHistory.vue';
import QueueList from '../../Components/QueueList.vue';
import SearchPanel from '../../Components/SearchPanel.vue';

const props = defineProps({
    party: {type: Object, required: true},
    membership: {type: Object, required: true},
    section: {type: String, default: 'queue'},
    canManage: {type: Boolean, default: false},
    canManageBlocklist: {type: Boolean, default: false},
    readOnly: {type: Boolean, default: false},
    nowPlaying: {type: Object, default: null},
    ratablePlay: {type: Object, default: null},
    memberVotes: {type: Object, default: null},
    upNext: {type: Object, default: null},
    startedAt: {type: String, default: null},
    queue: {type: Array, default: () => []},
    history: {type: Object, default: null},
    filters: {type: Object, default: () => ({})},
    search_query: {type: String, default: ''},
    results: {type: Array, default: null},
    search_error: {type: String, default: null},
    search_page: {type: Number, default: 1},
    search_has_more: {type: Boolean, default: false},
    enabled_mods: {type: Array, default: () => []},
    spotify_link_needed: {type: Boolean, default: false},
    spotify_link_url: {type: String, default: null},
    spotify_search_relink_needed: {type: Boolean, default: false},
});

usePartyPresence(props.party.code);

provide('enabledMods', computed(() => props.enabled_mods));

const channelName = `party.${props.party.code}`;
const memberChannelName = `${channelName}.member.${props.membership.id}`;
const KNOWN_PAYLOAD_VERSION = 1;

const votesByRequest = () => ({
    ...Object.fromEntries(props.queue.filter((entry) => entry.my_vote !== undefined).map((entry) => [entry.id, entry.my_vote])),
    ...Object.fromEntries((props.memberVotes?.votes ?? []).map((vote) => [vote.request_id, vote.value])),
});
const ownedRequestIds = () => props.queue.filter((entry) => entry.is_mine === true).map((entry) => entry.id);

const liveState = ref(props.party.state);
const toasts = ref([]);
let toastId = 0;
const pushToast = (message) => {
    const id = ++toastId;
    toasts.value = [...toasts.value, {id, message}];
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 6000);
};
const banned = ref(props.membership.banned);

const liveNowPlaying = ref(props.nowPlaying);
const liveUpNext = ref(props.upNext);
const liveQueue = ref(props.queue);
const liveStartedAt = ref(props.startedAt);
const liveMode = ref(props.party.selection_mode);
const queueSearch = ref('');
const submitQueueSearch = () => {
    const q = queueSearch.value.trim();
    if (q !== '') {
        router.get(`/parties/${props.party.code}/search`, {q});
    }
};
const now = ref(Date.now());
let clock = null;
let publicChannel = null;
const applyStateChange = (payload) => {
    liveState.value = payload.state;
};
const myVotes = ref(votesByRequest());
const myRequestIds = ref(ownedRequestIds());

watch(
    () => [props.nowPlaying, props.upNext, props.queue, props.startedAt, props.party.selection_mode, props.memberVotes, props.party.state, props.membership.banned],
    () => {
        liveState.value = props.party.state;
        banned.value = props.membership.banned;
        liveNowPlaying.value = props.nowPlaying;
        liveUpNext.value = props.upNext;
        liveQueue.value = props.queue;
        liveStartedAt.value = props.startedAt;
        liveMode.value = props.party.selection_mode;
        myVotes.value = votesByRequest();
        myRequestIds.value = ownedRequestIds();
    },
);

const shownQueue = computed(() => liveQueue.value.map((entry) => ({...entry, my_vote: myVotes.value[entry.id] ?? 0, is_mine: myRequestIds.value.includes(entry.id)})));
const eta = computed(() => estimateQueue({
    startedAt: liveStartedAt.value,
    nowPlaying: liveNowPlaying.value,
    upNext: liveUpNext.value,
    queue: liveQueue.value,
    mode: liveMode.value,
    now: now.value,
}));

const resync = () => {
    router.reload({only: ['party', 'queue', 'nowPlaying', 'upNext', 'startedAt', 'ratablePlay', 'memberVotes'], preserveScroll: true, async: true});
};

const applyQueueUpdate = (payload) => {
    if (payload.version > KNOWN_PAYLOAD_VERSION) {
        resync();

        return;
    }
    liveNowPlaying.value = payload.now_playing;
    liveUpNext.value = payload.up_next;
    liveQueue.value = payload.queue;
    liveStartedAt.value = payload.started_at ?? null;
    liveMode.value = payload.selection_mode ?? liveMode.value;
};

onMounted(() => {
    clock = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    publicChannel = window.Echo?.channel(channelName);
    publicChannel?.listen('.queue.updated', applyQueueUpdate).listen('.party.state_changed', applyStateChange);
    window.Echo?.private(memberChannelName)
        .listen('.member.vote_changed', (payload) => {
            myVotes.value = {...myVotes.value, [payload.request_id]: payload.value};
        })
        .listen('.request.rejected', (payload) => {
            pushToast(payload.reason ? `Your request was rejected: ${payload.reason}` : 'Your request was rejected.');
        })
        .listen('.request.decided', (payload) => {
            if (payload.status === 'queued') {
                myRequestIds.value = [...myRequestIds.value, payload.request_id];
                pushToast('Your request was approved.');
            }
        })
        .listen('.member.banned', () => {
            banned.value = true;
            pushToast('You have been banned from this party.');
        });
    window.addEventListener(RESYNC_EVENT, resync);
});

onBeforeUnmount(() => {
    clearInterval(clock);
    window.removeEventListener(RESYNC_EVENT, resync);
    publicChannel?.stopListening?.('.queue.updated', applyQueueUpdate);
    publicChannel?.stopListening?.('.party.state_changed', applyStateChange);
    window.Echo?.leave(memberChannelName);
});

const isStaff = computed(() => !banned.value && ['host', 'moderator'].includes(props.membership.role));
const canViewLog = isStaff;

const page = usePage();
const transitionError = computed(() => page.props.errors?.fallback_playlist_id ?? page.props.errors?.state ?? null);
const transitioning = ref(false);
const transition = (action) => {
    router.post(`/parties/${props.party.code}/${action}`, {}, {
        preserveScroll: true,
        onStart: () => {
            transitioning.value = true;
        },
        onFinish: () => {
            transitioning.value = false;
        },
    });
};

const copied = ref(false);
const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.party.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const stateClass = computed(
    () =>
        ({
            live: 'bg-accent text-white',
            paused: 'border border-border text-muted',
            ended: 'bg-danger text-white',
        })[liveState.value] ?? 'border border-border text-muted',
);

const readOnlyMessage = computed(() =>
    banned.value ? 'You have been banned from this party.' : 'This party has ended.',
);

const ratingLocked = computed(() => banned.value || liveState.value === 'ended');
</script>

<template>
    <Head :title="party.name" />
    <div v-if="toasts.length > 0" class="fixed right-4 top-4 z-50 flex flex-col gap-2" data-testid="toasts">
        <p v-for="toast in toasts" :key="toast.id" role="status" data-testid="toast" class="rounded border border-border bg-surface px-4 py-3 text-sm shadow">{{ toast.message }}</p>
    </div>
    <div v-if="spotify_link_needed" role="alert" data-testid="spotify-link-warning" class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-4 py-3 text-sm">
        <span>Playback needs a linked Spotify account.</span>
        <Link v-if="spotify_link_url" :href="spotify_link_url" class="flex min-h-11 items-center rounded border border-border px-3 hover:text-primary">Link Spotify</Link>
    </div>
    <div v-if="spotify_search_relink_needed" role="alert" data-testid="spotify-search-relink-warning" class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-4 py-3 text-sm">
        <span>Your Spotify Search account needs re-linking. Guest search is using your playback account instead.</span>
        <Link v-if="spotify_link_url" :href="spotify_link_url" class="flex min-h-11 items-center rounded border border-border px-3 hover:text-primary">Re-link Spotify Search</Link>
    </div>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 pb-6 pt-6 md:gap-6 md:px-0">
        <header v-if="section === 'party'" data-testid="party-card" class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ party.name }}</h1>
                <span
                    data-testid="party-state"
                    class="rounded px-2 py-0.5 text-xs font-medium capitalize"
                    :class="stateClass"
                >{{ liveState }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span data-testid="party-code" class="text-4xl font-semibold tracking-widest">{{ party.code }}</span>
                <button
                    type="button"
                    class="flex min-h-11 items-center gap-2 rounded border border-border px-3 text-sm hover:text-primary"
                    @click="copyCode"
                >
                    <Icon name="copy" />
                    <span aria-live="polite">{{ copied ? 'Copied' : 'Copy' }}</span>
                </button>
            </div>
        </header>

        <div v-if="canManage" class="flex flex-col gap-2" data-testid="lifecycle-controls">
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="liveState === 'paused'"
                    type="button"
                    data-testid="go-live"
                    :disabled="transitioning"
                    class="min-h-11 rounded bg-accent px-4 text-sm font-medium text-white disabled:opacity-50"
                    @click="transition('live')"
                >Go Live</button>
                <button
                    v-if="liveState === 'live'"
                    type="button"
                    data-testid="pause-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-border px-4 text-sm disabled:opacity-50"
                    @click="transition('pause')"
                >Pause</button>
                <button
                    v-if="liveState !== 'ended'"
                    type="button"
                    data-testid="end-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-danger px-4 text-sm text-danger disabled:opacity-50"
                    @click="transition('end')"
                >End</button>
                <button
                    v-if="liveState === 'ended'"
                    type="button"
                    data-testid="reopen-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-border px-4 text-sm disabled:opacity-50"
                    @click="transition('reopen')"
                >Reopen</button>
            </div>
            <p v-if="transitionError" role="alert" data-testid="lifecycle-error" class="text-sm text-danger">{{ transitionError }}</p>
        </div>

        <Link
            v-if="canManage && party.playerKind === 'browser'"
            :href="`/parties/${party.code}/player`"
            data-testid="open-browser-player"
            class="flex min-h-11 items-center self-start rounded border border-border px-3 text-sm hover:text-primary"
        >Open player</Link>

        <div
            v-if="readOnly"
            role="status"
            data-testid="read-only-banner"
            class="rounded border border-danger bg-surface px-4 py-3 text-sm text-danger"
        >
            {{ readOnlyMessage }}
        </div>

        <section :class="['queue', 'search', 'history'].includes(section) ? '' : 'rounded border border-border bg-surface px-5 py-5'">
            <dl v-if="section === 'party'" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted">Code</dt>
                <dd>{{ party.code }}</dd>
                <dt class="text-muted">Provider</dt>
                <dd>{{ party.musicProvider }}</dd>
                <dt class="text-muted">Player</dt>
                <dd>{{ party.playerKind }}</dd>
                <dt class="text-muted">Role</dt>
                <dd class="capitalize" data-testid="party-role">Your role: {{ membership.role }}</dd>
                <template v-if="!membership.banned">
                    <dt class="text-muted">Members</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/members`" class="hover:text-primary" data-testid="party-members-link">Party Members</Link>
                    </dd>
                    <dt class="text-muted">Stats</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/stats`" class="hover:text-primary" data-testid="party-stats-link">Live Stats</Link>
                    </dd>
                </template>
                <template v-if="canManage && !membership.banned">
                    <dt class="text-muted">Settings</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/settings`" class="hover:text-primary" data-testid="party-settings-link">Party Settings</Link>
                    </dd>
                </template>
                <template v-if="canManage && !membership.banned">
                    <dt class="text-muted">Mods</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/mods`" class="hover:text-primary" data-testid="party-mods-link">Party Mods</Link>
                    </dd>
                </template>
                <template v-if="canManageBlocklist && !membership.banned">
                    <dt class="text-muted">Blocklist</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/blocklist`" class="hover:text-primary" data-testid="party-blocklist-link">Party Blocklist</Link>
                    </dd>
                </template>
                <template v-if="canViewLog">
                    <dt class="text-muted">Log</dt>
                    <dd>
                        <Link :href="`/parties/${party.code}/log`" class="hover:text-primary" data-testid="party-log-link">Party Log</Link>
                    </dd>
                </template>
            </dl>
            <template v-else-if="section === 'queue'">
                <UpNextCard :up-next="liveUpNext" :plays-at="eta.upNextAt" :now="now" class="mb-6" />
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 data-testid="queue-heading" class="text-base font-bold md:text-lg">{{ liveMode === 'weighted' ? 'Upcoming Songs' : 'Queue' }}</h2>
                    <form data-testid="queue-search-form" class="flex gap-2" @submit.prevent="submitQueueSearch">
                        <div class="relative">
                            <input v-model="queueSearch" type="search" data-testid="queue-search-input" placeholder="Search..." aria-label="Search for a track" class="min-h-11 w-48 rounded border border-border bg-surface py-0 pl-3 pr-10 text-sm md:w-64" />
                            <Icon name="musicSearch" data-testid="queue-search-icon" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                        </div>
                        <button type="submit" data-testid="queue-search-submit" class="min-h-11 rounded bg-primary px-4 text-sm font-medium text-white">Search</button>
                    </form>
                </div>
                <p v-if="liveMode === 'weighted'" data-testid="queue-weighted-hint" class="mb-1 text-sm text-muted">The more upvotes a track has, the more likely it is to play.</p>
                <p v-if="eta.totalMs > 0" data-testid="queue-total" class="mb-3 text-sm text-muted md:mb-4">Total {{ formatTotal(eta.totalMs) }}</p>
                <QueueList :queue="shownQueue" :starts-at="eta.startsAt" :now="now" :party-code="party.code" :downvotes-enabled="party.downvotes !== false" :read-only="readOnly" :can-moderate="isStaff" />
            </template>
            <template v-else-if="section === 'search'">
                <h2 class="mb-3 text-base font-bold md:mb-4 md:text-lg">Search</h2>
                <SearchPanel :party="party" :results="results" :search-query="search_query" :search-error="search_error" :search-page="search_page" :search-has-more="search_has_more" :read-only="readOnly" />
            </template>
            <template v-else-if="section === 'history'">
                <h2 class="mb-3 text-base font-bold md:mb-4 md:text-lg">Songs</h2>
                <div v-if="party.playlistCsvUrl || party.historyPlaylistUrl" class="mb-3 flex flex-wrap gap-3 text-sm" data-testid="history-links">
                    <a v-if="party.playlistCsvUrl" :href="party.playlistCsvUrl" download data-testid="download-playlist-csv" class="flex min-h-11 items-center rounded border border-border px-4 hover:text-primary">Download playlist (CSV)</a>
                    <a v-if="party.historyPlaylistUrl" :href="party.historyPlaylistUrl" target="_blank" rel="noopener noreferrer" data-testid="history-playlist-link" class="flex min-h-11 items-center rounded border border-border px-4 hover:text-primary">Open History Playlist</a>
                </div>
                <PlayedHistory :history="history" :filters="filters" :party-code="party.code" :read-only="ratingLocked" />
            </template>
        </section>
    </div>
</template>

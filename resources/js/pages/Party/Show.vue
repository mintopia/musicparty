<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import Icon from '../../Components/Icon.vue';
import NowPlayingBanner from '../../Components/NowPlayingBanner.vue';
import UpNextCard from '../../Components/UpNextCard.vue';
import PlayedHistory from '../../Components/PlayedHistory.vue';
import QueueList from '../../Components/QueueList.vue';
import SearchPanel from '../../Components/SearchPanel.vue';
import PlaybackControls from '../../Components/PlaybackControls.vue';

const props = defineProps({
    party: {type: Object, required: true},
    membership: {type: Object, required: true},
    section: {type: String, default: 'queue'},
    canManage: {type: Boolean, default: false},
    canManageBlocklist: {type: Boolean, default: false},
    readOnly: {type: Boolean, default: false},
    nowPlaying: {type: Object, default: null},
    myRating: {type: Number, default: 0},
    ratablePlay: {type: Object, default: null},
    upNext: {type: Object, default: null},
    queue: {type: Array, default: () => []},
    history: {type: Object, default: null},
    filters: {type: Object, default: () => ({})},
    search_query: {type: String, default: ''},
    results: {type: Array, default: null},
    search_error: {type: String, default: null},
});

const channelName = `party.${props.party.code}`;

onMounted(() => {
    window.Echo?.channel(channelName).listen('Party.QueueUpdatedEvent', () => {
        router.reload({only: ['queue', 'nowPlaying', 'upNext', 'myRating'], preserveScroll: true});
    });
});

onBeforeUnmount(() => {
    window.Echo?.leave(channelName);
});

const canViewLog = computed(
    () => !props.membership.banned && ['host', 'moderator'].includes(props.membership.role),
);

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
        })[props.party.state] ?? 'border border-border text-muted',
);

const readOnlyMessage = computed(() =>
    props.membership.banned ? 'You have been banned from this party.' : 'This party has ended.',
);

const ratingLocked = computed(() => props.membership.banned || props.party.state === 'ended');
</script>

<template>
    <Head :title="party.name" />
    <NowPlayingBanner
        v-if="section === 'queue' || section === 'history'"
        :now-playing="nowPlaying"
        :ratable-play="ratablePlay"
        :my-rating="myRating"
        :party-code="party.code"
        :read-only="ratingLocked"
    />
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 pb-6 pt-6 md:gap-6 md:px-0">
        <header class="flex flex-col gap-3 rounded border border-border bg-surface px-5 py-5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ party.name }}</h1>
                <span
                    data-testid="party-state"
                    class="rounded px-2 py-0.5 text-xs font-medium capitalize"
                    :class="stateClass"
                >{{ party.state }}</span>
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
                    v-if="party.state === 'paused'"
                    type="button"
                    data-testid="go-live"
                    :disabled="transitioning"
                    class="min-h-11 rounded bg-accent px-4 text-sm font-medium text-white disabled:opacity-50"
                    @click="transition('live')"
                >Go Live</button>
                <button
                    v-if="party.state === 'live'"
                    type="button"
                    data-testid="pause-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-border px-4 text-sm disabled:opacity-50"
                    @click="transition('pause')"
                >Pause</button>
                <button
                    v-if="party.state !== 'ended'"
                    type="button"
                    data-testid="end-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-danger px-4 text-sm text-danger disabled:opacity-50"
                    @click="transition('end')"
                >End</button>
                <button
                    v-if="party.state === 'ended'"
                    type="button"
                    data-testid="reopen-party"
                    :disabled="transitioning"
                    class="min-h-11 rounded border border-border px-4 text-sm disabled:opacity-50"
                    @click="transition('reopen')"
                >Reopen</button>
            </div>
            <p v-if="transitionError" role="alert" data-testid="lifecycle-error" class="text-sm text-danger">{{ transitionError }}</p>
        </div>

        <PlaybackControls v-if="canManage && !readOnly" :party-code="party.code" />

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
                <UpNextCard :up-next="upNext" class="mb-6" />
                <h2 class="mb-3 text-base font-bold md:mb-4 md:text-lg">Queue</h2>
                <QueueList :queue="queue" :party-code="party.code" :downvotes-enabled="party.downvotes !== false" :read-only="readOnly" />
            </template>
            <template v-else-if="section === 'search'">
                <h2 class="mb-3 text-base font-bold md:mb-4 md:text-lg">Search</h2>
                <SearchPanel :party="party" :results="results" :search-query="search_query" :search-error="search_error" :read-only="readOnly" />
            </template>
            <template v-else-if="section === 'history'">
                <h2 class="mb-3 text-base font-bold md:mb-4 md:text-lg">Songs</h2>
                <PlayedHistory :history="history" :filters="filters" :party-code="party.code" :read-only="ratingLocked" />
            </template>
        </section>
    </div>
</template>

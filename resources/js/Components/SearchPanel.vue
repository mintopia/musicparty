<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref} from 'vue';
import Icon from './Icon.vue';
import TrackLink from './TrackLink.vue';
import TrackThumb from './TrackThumb.vue';
import {formatDuration} from '../lib/format';

const props = defineProps({
    party: {type: Object, required: true},
    results: {type: Array, default: null},
    searchQuery: {type: String, default: ''},
    searchError: {type: String, default: null},
    searchPage: {type: Number, default: 1},
    searchHasMore: {type: Boolean, default: false},
    readOnly: {type: Boolean, default: false},
});

const query = ref(props.searchQuery);
const requested = ref({});
const pending = ref(null);
const errors = ref({});
let timer = null;

const searchProps = ['results', 'search_query', 'search_error', 'search_page', 'search_has_more'];

const runSearch = (page = 1) => {
    const term = query.value.trim();
    const params = term === '' ? {} : {q: term};
    if (term !== '' && page > 1) {
        params.page = page;
    }
    router.get(`/parties/${props.party.code}/search`, params, {preserveState: true, preserveScroll: true, replace: true, only: searchProps});
};

const nextPage = () => runSearch(props.searchPage + 1);

const starCount = (track) => {
    const popularity = Number(track.popularity);
    return Number.isFinite(popularity) ? Math.min(5, Math.max(0, Math.round(popularity / 20))) : 0;
};

const onInput = () => {
    clearTimeout(timer);
    timer = setTimeout(() => runSearch(), 300);
};

const submitSearch = () => {
    clearTimeout(timer);
    runSearch();
};

onBeforeUnmount(() => clearTimeout(timer));

const form = useForm({provider_track_id: ''});

const request = (track) => {
    if (props.readOnly || pending.value) {
        return;
    }
    pending.value = track.provider_track_id;
    errors.value = {};
    form.provider_track_id = track.provider_track_id;
    form.post(`/parties/${props.party.code}/requests`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            requested.value = {...requested.value, [track.provider_track_id]: true};
        },
        onError: (e) => {
            errors.value = {[track.provider_track_id]: e.provider_track_id ?? e.request ?? 'Could not request this track.'};
        },
        onFinish: () => {
            pending.value = null;
        },
    });
};

const downvotesEnabled = computed(() => props.party.downvotes === true);

const buttonClass = (active, activeClass) => ['flex min-h-11 min-w-11 items-center justify-center rounded disabled:opacity-60', active ? activeClass : 'text-muted hover:text-text'];

const vote = (track, direction) => {
    if (props.readOnly || pending.value || track.queued_request_id == null) {
        return;
    }
    const value = direction === 'up' ? 1 : -1;
    const url = `/parties/${props.party.code}/requests/${track.queued_request_id}/vote`;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = track.provider_track_id;
            errors.value = {};
        },
        onError: (e) => {
            errors.value = {[track.provider_track_id]: e.vote ?? e.value ?? 'Could not record your vote.'};
        },
        onFinish: () => {
            pending.value = null;
        },
    };

    if (track.my_vote === value) {
        router.delete(url, options);
    } else {
        router.put(url, {value: direction}, options);
    }
};

const isIneligible = (track) => !track.queued && track.eligible === false;

const label = (track) => {
    if (requested.value[track.provider_track_id]) {
        return track.queued ? 'Upvoted' : 'Added';
    }
    return track.queued ? 'Queued' : 'Add';
};

const hasResults = computed(() => Array.isArray(props.results));
</script>

<template>
    <div class="flex flex-col gap-4">
        <form class="flex gap-2" role="search" @submit.prevent="submitSearch">
            <label class="sr-only" for="track-search">Search tracks</label>
            <input
                id="track-search"
                v-model="query"
                type="search"
                data-testid="search-input"
                placeholder="Search for a track"
                class="min-h-11 min-w-0 flex-1 rounded border border-border bg-surface px-3 text-sm text-text placeholder:text-muted focus:border-primary focus:outline-none"
                @input="onInput"
            />
            <button type="submit" class="min-h-11 rounded bg-primary px-5 text-sm font-medium text-white hover:opacity-90">Search</button>
        </form>

        <p v-if="searchError" role="alert" data-testid="search-error" class="text-sm text-danger">{{ searchError }}</p>
        <p v-else-if="!hasResults" class="text-sm text-muted">Search for a track to add it to the queue.</p>
        <p v-else-if="results.length === 0" data-testid="search-empty" class="text-sm text-muted">No tracks found.</p>
        <template v-else>
            <h2 class="text-base font-bold md:text-lg">Results</h2>
            <ul data-testid="search-results" class="divide-y divide-border overflow-hidden rounded border border-border bg-surface">
                <li v-for="track in results" :key="track.provider_track_id" data-testid="search-result" class="px-3 py-3 md:px-5 md:py-4">
                    <div class="flex items-center gap-3 md:gap-4">
                        <TrackLink :href="track.provider_url"><TrackThumb :src="track.artwork_url" /></TrackLink>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm text-primary"><TrackLink :href="track.provider_url">{{ track.title }}</TrackLink></div>
                            <div class="truncate text-sm text-muted">{{ track.artists.join(', ') }}</div>
                            <div v-if="track.queued" data-testid="requested-by" class="truncate text-sm text-muted">Requested by {{ track.requested_by }}</div>
                            <div v-else class="truncate text-xs text-muted">{{ track.album }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span data-testid="popularity-stars" role="img" :aria-label="`${starCount(track)} of 5 stars`" :data-stars="starCount(track)" class="flex items-center text-primary">
                                    <Icon v-for="n in 5" :key="n" name="star" :filled="n <= starCount(track)" class="!h-4 !w-4" :class="n <= starCount(track) ? '' : 'text-muted'" />
                                </span>
                                <a v-if="track.provider_url" :href="track.provider_url" target="_blank" rel="noopener" data-testid="view-on-spotify" class="text-xs text-primary hover:underline">View on Spotify</a>
                            </div>
                        </div>
                        <span class="hidden text-sm tabular-nums text-muted sm:inline">{{ formatDuration(track.duration_ms) }}</span>
                        <div v-if="track.queued_request_id != null" class="flex items-center">
                            <button
                                type="button"
                                data-testid="vote-up"
                                :aria-label="`${downvotesEnabled ? 'Upvote' : 'Like'} ${track.title}`"
                                :aria-pressed="track.my_vote === 1"
                                :disabled="readOnly || pending !== null"
                                :class="buttonClass(track.my_vote === 1, 'text-accent')"
                                @click="vote(track, 'up')"
                            >
                                <Icon :name="downvotesEnabled ? 'voteUp' : 'heart'" :filled="track.my_vote === 1" />
                            </button>
                            <span data-testid="result-score" class="min-w-8 text-center text-sm tabular-nums" :class="{'text-accent': track.score > 0, 'text-danger': track.score < 0}">{{ track.score }}</span>
                            <button
                                v-if="downvotesEnabled"
                                type="button"
                                data-testid="vote-down"
                                :aria-label="`Downvote ${track.title}`"
                                :aria-pressed="track.my_vote === -1"
                                :disabled="readOnly || pending !== null"
                                :class="buttonClass(track.my_vote === -1, 'text-danger')"
                                @click="vote(track, 'down')"
                            >
                                <Icon :name="track.my_vote === -1 ? 'voteDown' : 'voteDownTray'" :filled="track.my_vote === -1" />
                            </button>
                        </div>
                        <button
                            v-else
                            type="button"
                            data-testid="request-button"
                            :disabled="readOnly || pending !== null || requested[track.provider_track_id] || isIneligible(track)"
                            :title="isIneligible(track) ? track.reason : undefined"
                            class="flex min-h-11 min-w-11 items-center justify-center gap-2 rounded px-4 text-sm font-medium disabled:opacity-60"
                            :class="requested[track.provider_track_id] ? 'border border-border text-text' : 'bg-primary text-white'"
                            @click="request(track)"
                        >
                            <Icon :name="requested[track.provider_track_id] ? 'queue' : 'musicPlus'" />
                            <span>{{ label(track) }}</span>
                        </button>
                    </div>
                    <p v-if="isIneligible(track)" data-testid="ineligible-reason" class="mt-2 text-sm text-muted">{{ track.reason }}</p>
                    <p v-if="errors[track.provider_track_id]" role="alert" data-testid="request-error" class="mt-2 text-sm text-danger">{{ errors[track.provider_track_id] }}</p>
                </li>
            </ul>
            <button v-if="searchHasMore" type="button" data-testid="search-next" class="min-h-11 self-center rounded border border-border px-5 text-sm font-medium text-text hover:opacity-90" @click="nextPage">Load more</button>
        </template>
    </div>
</template>

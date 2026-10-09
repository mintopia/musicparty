<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref} from 'vue';
import Icon from './Icon.vue';
import TrackThumb from './TrackThumb.vue';
import {formatDuration} from '../lib/format';

const props = defineProps({
    party: {type: Object, required: true},
    results: {type: Array, default: null},
    searchQuery: {type: String, default: ''},
    readOnly: {type: Boolean, default: false},
});

const query = ref(props.searchQuery);
const requested = ref({});
const pending = ref(null);
const errors = ref({});
let timer = null;

const runSearch = () => {
    router.get(
        `/parties/${props.party.code}/search`,
        query.value.trim() === '' ? {} : {q: query.value.trim()},
        {preserveState: true, preserveScroll: true, replace: true, only: ['results', 'search_query']},
    );
};

const onInput = () => {
    clearTimeout(timer);
    timer = setTimeout(runSearch, 300);
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

const label = (track) => {
    if (requested.value[track.provider_track_id]) {
        return track.queued ? 'Upvoted' : 'Requested';
    }
    return track.queued ? 'Queued' : 'Request';
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
            <button type="submit" class="min-h-11 rounded bg-primary px-5 text-sm font-medium text-white">Search</button>
        </form>

        <p v-if="!hasResults" class="text-sm text-muted">Search for a track to add it to the queue.</p>
        <p v-else-if="results.length === 0" data-testid="search-empty" class="text-sm text-muted">No tracks found.</p>
        <template v-else>
            <h2 class="text-lg font-semibold">Results</h2>
            <ul data-testid="search-results" class="divide-y divide-border overflow-hidden rounded border border-border">
                <li v-for="track in results" :key="track.provider_track_id" data-testid="search-result" class="px-3 py-3 md:px-5">
                    <div class="flex items-center gap-3">
                        <TrackThumb :src="track.artwork_url" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ track.title }}</div>
                            <div class="truncate text-sm text-muted">{{ track.artists.join(', ') }}</div>
                            <div class="truncate text-xs text-muted">{{ track.album }}</div>
                        </div>
                        <span class="hidden text-sm text-muted sm:inline">{{ formatDuration(track.duration_ms) }}</span>
                        <button
                            type="button"
                            data-testid="request-button"
                            :disabled="readOnly || pending !== null || requested[track.provider_track_id]"
                            class="flex min-h-11 min-w-11 items-center justify-center gap-2 rounded px-4 text-sm font-medium disabled:opacity-60"
                            :class="track.queued || requested[track.provider_track_id] ? 'border border-border text-text' : 'bg-primary text-white'"
                            @click="request(track)"
                        >
                            <Icon :name="track.queued || requested[track.provider_track_id] ? 'queue' : 'plus'" />
                            <span>{{ label(track) }}</span>
                        </button>
                    </div>
                    <p v-if="errors[track.provider_track_id]" role="alert" data-testid="request-error" class="mt-2 text-sm text-danger">{{ errors[track.provider_track_id] }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>

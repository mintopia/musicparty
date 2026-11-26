<script setup lang="ts">
import {Deferred, Head, router, usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, ref, watch} from 'vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchResultsSection from '@/components/SearchResultsSection.vue';
import {useEcho} from '@/composables/useEcho';
import {useQueueSync} from '@/composables/useQueueSync';
import {useRequestTrack} from '@/composables/useRequestTrack';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {flaggedWithdrawUrl} from '@/lib/flagged';
import {show, search} from '@/routes/parties';
import {show as showAlbum} from '@/routes/parties/albums';
import {show as showArtist} from '@/routes/parties/artists';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import type {LegacyPartyState, SearchError, SearchResults, SearchType, SharedProps, TrackAnnotation} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; downvotes: boolean; canManage: boolean};
    params: {query: string | null; perPage: number; type?: SearchType | null};
    results?: SearchResults | SearchError | null;
    playerState: LegacyPartyState;
}>();

const query = ref(props.params.query ?? '');
const message = ref<string | null>(null);
const current = ref<SearchResults | SearchError | null | undefined>(props.results);
const searching = ref(false);
const loadingMore = ref(false);
const hasSearch = computed(() => props.params.query !== null || (current.value ?? null) !== null);
const {status, reason, request} = useRequestTrack(() => props.party.code);

watch(
    () => props.results,
    (results) => {
        current.value = results;
    },
);

const searchUrl = computed(() => search(props.party.code).url);
const queryString = computed(() => (query.value ? `?query=${encodeURIComponent(query.value)}` : ''));
const overviewUrl = computed(
    () => searchUrl.value + (props.params.query ? `?query=${encodeURIComponent(props.params.query)}` : ''),
);
const typeUrl = (type: SearchType): string => `${overviewUrl.value}${props.params.query ? '&' : '?'}type=${type}`;

function submit(): void {
    router.get(
        searchUrl.value,
        {
            query: query.value,
            perPage: props.params.perPage === 10 ? undefined : props.params.perPage,
        },
        {onStart: () => (searching.value = true), onFinish: () => (searching.value = false)},
    );
}

function loadMore(url: string): void {
    router.get(
        url,
        {},
        {
            only: ['results', 'params'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => (loadingMore.value = true),
            onFinish: () => (loadingMore.value = false),
        },
    );
}

function retry(): void {
    router.reload({
        only: ['results'],
        onStart: () => (searching.value = true),
        onFinish: () => (searching.value = false),
    });
}

useQueueSync(props.party.code, ['results']);

const authUser = usePage<SharedProps>().props.auth.user;
if (authUser) {
    const userChannel = useEcho().privateChannel(`user.${authUser.id}`) as {
        listen<T>(event: string, cb: (payload: T) => void): unknown;
    };
    userChannel.listen<{message: string; track?: {name?: string}}>('FlaggedRequestRejected', (payload) => {
        showMessage(
            `Your request${payload.track?.name ? ` for ${payload.track.name}` : ''} was rejected: ${payload.message}`,
        );
        router.reload({only: ['results']});
    });
}

function showMessage(text: string): void {
    message.value = text;
    setTimeout(() => {
        message.value = null;
    }, 5000);
}

async function requestTrack(track: TrackAnnotation): Promise<void> {
    await request(track.id);
    if (status.value === 'success') {
        router.reload({only: ['results']});
    } else if (status.value === 'blocked' || status.value === 'error') {
        showMessage(reason.value ?? 'This track cannot be requested.');
    }
}

async function withdraw(track: TrackAnnotation): Promise<void> {
    try {
        await axios.delete(
            flaggedWithdrawUrl(props.party.code, track.flagged_request_id ?? track.queue.upcoming_id ?? 0),
        );
        router.reload({only: ['results']});
    } catch {
        showMessage('This request could not be withdrawn.');
    }
}

async function vote(track: TrackAnnotation, value: -1 | 0 | 1): Promise<void> {
    const route = storeVote({party: props.party.code, upcomingsong: track.queue.upcoming_id as number});
    try {
        const response = await axios.request({url: route.url, method: route.method, data: {vote: value}});
        const data = response.data.data as {score: number; vote: number | null; user: string | null};
        track.queue = {...track.queue, score: data.score, vote: data.vote, requested_by: data.user};
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 400) {
            showMessage((error.response.data as {message: string}).message);
        }
    }
}
</script>

<template>
    <AppLayout :title="`Search - ${party.name}`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="searchUrl">Search</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Search - ${party.name}`" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Search</h1>
            </div>
            <form :action="searchUrl" method="get" @submit.prevent="submit">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <div class="input-icon">
                            <input
                                id="query"
                                v-model="query"
                                type="text"
                                class="form-control"
                                name="query"
                                placeholder="Search…"
                            />
                            <span class="input-icon-addon">
                                <i class="icon ti ti-music-search"></i>
                            </span>
                        </div>
                    </div>
                    <div class="flex-column ps-2">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>
            <template v-if="hasSearch">
                <SearchResultsSection v-if="searching" loading />
                <Deferred v-else data="results">
                    <template #fallback><SearchResultsSection loading :type="params.type ?? null" /></template>
                    <template #rescue><SearchResultsSection failed @retry="retry" /></template>
                    <SearchResultsSection
                        :results="current"
                        :query="params.query ?? ''"
                        :overview-url="overviewUrl"
                        :type-url="typeUrl"
                        :loading-more="loadingMore"
                        :can-downvote="party.downvotes"
                        :album-route="(id: string) => showAlbum({party: party.code, album: id}).url + queryString"
                        :artist-route="(id: string) => showArtist({party: party.code, artist: id}).url + queryString"
                        @request="requestTrack"
                        @withdraw="withdraw"
                        @vote="vote"
                        @retry="retry"
                        @load-more="loadMore"
                    />
                </Deferred>
            </template>
        </div>
        <div v-if="message" class="position-fixed z-3 p-2 top-50 start-50 translate-middle" data-test="message">
            <div class="alert alert-important alert-danger" role="alert">{{ message }}</div>
        </div>
    </AppLayout>
</template>

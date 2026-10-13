<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, ref} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import TrackList from '@/components/TrackList.vue';
import {useQueueSync} from '@/composables/useQueueSync';
import {useRequestTrack} from '@/composables/useRequestTrack';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import {show, search} from '@/routes/parties';
import {show as showArtist} from '@/routes/parties/artists';
import type {LegacyPartyState, Paginator, TrackAnnotation} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; downvotes: boolean; canManage: boolean};
    query: string | null;
    album: {
        id: string;
        name: string;
        image_url: string | null;
        artists: {id: string; name: string}[];
        release_year: string | null;
        track_count: number;
    } | null;
    tracks: Paginator<TrackAnnotation> | null;
    retry: boolean;
    playerState: LegacyPartyState;
}>();

useQueueSync(props.party.code, ['tracks']);
const message = ref<string | null>(null);
const {status, reason, request} = useRequestTrack(() => props.party.code);

const searchUrl = computed(() => {
    const url = search(props.party.code).url;

    return props.query ? `${url}?query=${encodeURIComponent(props.query)}` : url;
});
const title = computed(() => `${props.album?.name ?? 'Album'} - ${props.party.name}`);

function showMessage(text: string): void {
    message.value = text;
    setTimeout(() => {
        message.value = null;
    }, 5000);
}

async function requestTrack(track: TrackAnnotation): Promise<void> {
    await request(track.id);
    if (status.value === 'success') {
        router.reload({only: ['tracks']});
    } else if (status.value === 'blocked' || status.value === 'error') {
        showMessage(reason.value ?? 'This track cannot be requested.');
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
    <AppLayout :title="title">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item">
                <a :href="searchUrl">Search</a>
            </li>
            <li class="breadcrumb-item active">{{ album?.name ?? 'Album' }}</li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="title" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <EmptyState
                v-if="retry"
                title="Try again shortly"
                description="The music provider is busy right now."
                data-test="album-retry"
            >
                <a :href="searchUrl" class="btn btn-primary">Back to search</a>
            </EmptyState>
            <EmptyState
                v-else-if="album === null"
                title="Album not found"
                description="This album does not exist or is not available from this Party's music provider."
                data-test="album-not-found"
            >
                <a :href="searchUrl" class="btn btn-primary">Back to search</a>
            </EmptyState>
            <template v-else>
                <div class="page-header mt-0 d-flex align-items-center">
                    <img
                        v-if="album.image_url"
                        :src="album.image_url"
                        :alt="album.name"
                        class="rounded me-3"
                        width="160"
                        height="160"
                        data-test="album-art"
                    />
                    <div>
                        <h1 data-test="album-name">{{ album.name }}</h1>
                        <div class="text-secondary" data-test="album-artists">
                            <template v-for="(artist, index) in album.artists" :key="artist.id">
                                <template v-if="index > 0">, </template>
                                <span>{{ artist.name }}</span>
                            </template>
                        </div>
                        <div class="text-secondary" data-test="album-meta">
                            <template v-if="album.release_year">{{ album.release_year }} · </template>
                            {{ album.track_count }} {{ album.track_count === 1 ? 'track' : 'tracks' }}
                        </div>
                    </div>
                </div>
                <div v-if="tracks" class="card">
                    <TrackList
                        :tracks="tracks"
                        :can-downvote="party.downvotes"
                        empty-title="No tracks on this album"
                        :artist-route="
                            (id: string) =>
                                showArtist({party: party.code, artist: id}).url +
                                (query ? `?query=${encodeURIComponent(query)}` : '')
                        "
                        @request="requestTrack"
                        @vote="vote"
                    />
                </div>
            </template>
        </div>
        <div v-if="message" class="position-fixed z-3 p-2 top-50 start-50 translate-middle" data-test="message">
            <div class="alert alert-important alert-danger" role="alert">{{ message }}</div>
        </div>
    </AppLayout>
</template>

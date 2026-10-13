<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, ref} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import TrackList from '@/components/TrackList.vue';
import {useRequestTrack} from '@/composables/useRequestTrack';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {store as storeVote} from '@/routes/api/v1/parties/upcomingsongs/vote';
import {show, search} from '@/routes/parties';
import {show as showAlbum} from '@/routes/parties/albums';
import type {LegacyPartyState, Paginator, TrackAnnotation} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; downvotes: boolean; canManage: boolean};
    query: string | null;
    artist: {id: string; name: string; image_url: string | null} | null;
    tracks: Paginator<TrackAnnotation> | null;
    albums: Paginator<{id: string; name: string; image_url: string | null}> | null;
    retry: boolean;
    playerState: LegacyPartyState;
}>();

const message = ref<string | null>(null);
const {status, reason, request} = useRequestTrack(() => props.party.code);

const searchUrl = computed(() => {
    const url = search(props.party.code).url;

    return props.query ? `${url}?query=${encodeURIComponent(props.query)}` : url;
});
const queryString = computed(() => (props.query ? `?query=${encodeURIComponent(props.query)}` : ''));
const title = computed(() => `${props.artist?.name ?? 'Artist'} - ${props.party.name}`);

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
            <li class="breadcrumb-item active">{{ artist?.name ?? 'Artist' }}</li>
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
                data-test="artist-retry"
            >
                <a :href="searchUrl" class="btn btn-primary">Back to search</a>
            </EmptyState>
            <EmptyState
                v-else-if="artist === null"
                title="Artist not found"
                description="This artist does not exist or is not available from this Party's music provider."
                data-test="artist-not-found"
            >
                <a :href="searchUrl" class="btn btn-primary">Back to search</a>
            </EmptyState>
            <template v-else>
                <div class="page-header mt-0 d-flex align-items-center">
                    <img
                        v-if="artist.image_url"
                        :src="artist.image_url"
                        :alt="artist.name"
                        class="rounded-circle me-3"
                        width="160"
                        height="160"
                        data-test="artist-image"
                    />
                    <h1 data-test="artist-name">{{ artist.name }}</h1>
                </div>
                <h2 class="h3">Top tracks</h2>
                <div v-if="tracks" class="card mb-4">
                    <TrackList
                        :tracks="tracks"
                        :can-downvote="party.downvotes"
                        empty-title="No top tracks for this artist"
                        :album-route="(id: string) => showAlbum({party: party.code, album: id}).url + queryString"
                        @request="requestTrack"
                        @vote="vote"
                    />
                </div>
                <h2 class="h3">Albums and singles</h2>
                <EmptyState v-if="albums && albums.data.length === 0" title="No albums or singles" />
                <template v-else-if="albums">
                    <div class="row row-cards" data-test="artist-albums">
                        <div v-for="album in albums.data" :key="album.id" class="col-6 col-md-4 col-lg-3">
                            <a
                                :href="showAlbum({party: party.code, album: album.id}).url + queryString"
                                class="card card-link"
                            >
                                <img
                                    v-if="album.image_url"
                                    :src="album.image_url"
                                    :alt="album.name"
                                    class="card-img-top"
                                />
                                <div class="card-body">{{ album.name }}</div>
                            </a>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-center">
                        <Pagination :paginator="albums" />
                    </div>
                </template>
            </template>
        </div>
        <div v-if="message" class="position-fixed z-3 p-2 top-50 start-50 translate-middle" data-test="message">
            <div class="alert alert-important alert-danger" role="alert">{{ message }}</div>
        </div>
    </AppLayout>
</template>

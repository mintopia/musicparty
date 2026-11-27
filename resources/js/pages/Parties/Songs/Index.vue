<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';
import SortHeader from '@/components/SortHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {index} from '@/routes/parties/songs';
import type {LegacyPartyState, Paginator, When} from '@/types';

interface SongRow {
    id: number;
    name: string;
    artists: string[];
    albumName: string;
    imageUrl: string;
    votes: number;
    score: number;
    createdAt: When;
    queuedAt: When | null;
    url: string;
}

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    providerName: string;
    filters: {
        name?: string | null;
        artist?: string | null;
        album?: string | null;
        type?: string | null;
        order: string;
        order_direction: 'asc' | 'desc';
    };
    types: {code: string; name: string}[];
    songs: Paginator<SongRow> & {from: number | null; to: number | null; total: number};
}>();

const form = reactive({
    name: props.filters.name ?? '',
    artist: props.filters.artist ?? '',
    album: props.filters.album ?? '',
    type: props.filters.type ?? '',
});

const songsUrl = index(props.party.code).url;

const filtered = computed(() =>
    Boolean(props.filters.name || props.filters.artist || props.filters.album || props.filters.type),
);

function submit(): void {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''));
    router.get(songsUrl, {...query, order: props.filters.order, order_direction: props.filters.order_direction});
}
</script>

<template>
    <AppLayout :title="`Requests - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'Requests'}]" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Requests - ${party.name}`" />
        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form :action="songsUrl" method="get" class="card" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                    </div>
                    <div class="card-body">
                        <SearchTextField v-model="form.name" name="Name" property="name" />
                        <SearchTextField v-model="form.artist" name="Artist" property="artist" />
                        <SearchTextField v-model="form.album" name="Album" property="album" />
                        <SearchSelectField
                            v-model="form.type"
                            name="Type"
                            property="type"
                            :options="types"
                            value-key="code"
                            label-key="name"
                        />
                    </div>
                    <div class="card-footer d-flex">
                        <button class="btn btn-primary ms-auto" type="submit">Search</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-9 col-md-12">
                <div class="card">
                    <div v-if="songs.data.length > 0" class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>Song</th>
                                    <th class="text-center">
                                        <SortHeader
                                            title="Votes"
                                            field="votes"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                    <th class="text-center">
                                        <SortHeader
                                            title="Score"
                                            field="score"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Requested"
                                            field="created_at"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            :title="`Sent to ${providerName}`"
                                            field="queued_at"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="song in songs.data" :key="song.id" data-test="song-row">
                                    <td>
                                        <div class="d-flex">
                                            <div class="col-auto">
                                                <img
                                                    :src="song.imageUrl"
                                                    alt=""
                                                    loading="lazy"
                                                    decoding="async"
                                                    height="40"
                                                    width="40"
                                                    :title="song.albumName"
                                                />
                                            </div>
                                            <div class="col ps-2 text-wrap">
                                                <Link :href="song.url">{{ song.name }}</Link>
                                                <div class="text-secondary text-wrap">
                                                    {{ song.artists.join(', ') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ song.votes }}</td>
                                    <td class="text-center">{{ song.score }}</td>
                                    <td>
                                        <span :title="song.createdAt.iso">{{ song.createdAt.human }}</span>
                                    </td>
                                    <td>
                                        <span v-if="song.queuedAt" :title="song.queuedAt.iso">{{
                                            song.queuedAt.human
                                        }}</span>
                                        <span v-else class="text-muted">Not Sent to {{ providerName }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else-if="filtered"
                        icon="ti-music-off"
                        title="No matching requests"
                        description="Try a different name, artist, album or type."
                    >
                        <Link :href="songsUrl" class="btn btn-primary">Clear filters</Link>
                    </EmptyState>
                    <EmptyState
                        v-else
                        icon="ti-music-off"
                        title="No requests yet"
                        description="Songs requested at this party will appear here."
                    />
                    <div class="card-footer d-flex align-items-center">
                        <p class="m-0 mb-1 text-muted">
                            Showing <span>{{ songs.from ?? 0 }}</span> to <span>{{ songs.to ?? 0 }}</span> of
                            <span>{{ songs.total }}</span> entries
                        </p>
                        <div class="m-0 ms-auto"><Pagination :paginator="songs" /></div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

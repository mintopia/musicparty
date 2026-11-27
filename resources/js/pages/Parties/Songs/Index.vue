<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
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
        q?: string | null;
        name?: string | null;
        artist?: string | null;
        album?: string | null;
        type?: string | null;
        order: string;
        order_direction: 'asc' | 'desc';
    };
    songs: Paginator<SongRow> & {from: number | null; to: number | null; total: number};
}>();

const songsUrl = index(props.party.code).url;

const FILTER_KEYS = ['q', 'name', 'artist', 'album', 'type'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];

const query = ref(props.filters.q ?? '');

const filtered = computed(() => FILTER_KEYS.some((key) => Boolean(props.filters[key])));

const chips = computed<SearchChip[]>(() => {
    const result: SearchChip[] = [
        {key: 'type:queued', label: 'Waiting', pressed: props.filters.type === 'queued'},
        {key: 'type:sent', label: 'Sent to Player', pressed: props.filters.type === 'sent'},
    ];
    for (const key of ['name', 'artist', 'album'] as const) {
        const value = props.filters[key];
        if (value) {
            result.push({
                key: `${key}:${value}`,
                label: `${key[0].toUpperCase()}${key.slice(1)}: ${value}`,
                pressed: true,
            });
        }
    }

    return result;
});

function visit(overrides: Partial<Record<FilterKey, string>>): void {
    const next: Record<string, string | number> = {};
    for (const key of FILTER_KEYS) {
        const value = key in overrides ? overrides[key] : props.filters[key];
        if (value) {
            next[key] = value;
        }
    }
    router.get(songsUrl, {...next, order: props.filters.order, order_direction: props.filters.order_direction});
}

function onSearch(value: string): void {
    query.value = value;
    visit({q: value});
}

function toggleChip(chip: string): void {
    const [key, value] = [chip.slice(0, chip.indexOf(':')) as FilterKey, chip.slice(chip.indexOf(':') + 1)];
    visit({[key]: props.filters[key] === value ? '' : value});
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
        <div class="page-header mt-0">
            <h1>Requests</h1>
        </div>

        <SearchBar
            :model-value="query"
            placeholder="Search tracks, artists or albums"
            label="Search Requests"
            :result-count="songs.total"
            noun="Requests"
            :chips="chips"
            @update:model-value="onSearch"
            @toggle-chip="toggleChip"
        />
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
                                    title="Sent to Player"
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
                                <span v-if="song.queuedAt" :title="song.queuedAt.iso">{{ song.queuedAt.human }}</span>
                                <span v-else class="text-muted">Not yet</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="filtered"
                icon="ti-music-off"
                title="No matching requests"
                :description="filters.q ? `Nothing matches “${filters.q}”.` : 'Nothing matches these filters.'"
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
    </AppLayout>
</template>

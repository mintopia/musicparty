<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import RowMenu from '@/components/RowMenu.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import SortHeader from '@/components/SortHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {index} from '@/routes/parties/songs';
import type {LegacyPartyState, Paginator, When} from '@/types';
import {hasToken, parseSearch, serializeSearch, toggleToken} from '@/utils/searchTokens';

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
    scheduledLabel?: string | null;
    flagged?: boolean;
    requestedBy?: {nickname: string; isYou: boolean} | null;
    url: string;
    removeUrl?: string;
}

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    providerName: string;
    filters: {
        q?: string | null;
        order: string;
        order_direction: 'asc' | 'desc';
    };
    counts?: {total: number; sent: number; waiting: number; scheduled: number; flagged: number; mine: number};
    songs: Paginator<SongRow> & {from: number | null; to: number | null; total: number};
}>();

const songsUrl = index(props.party.code).url;

const TOKEN_KEYS = ['sent', 'scheduled', 'flagged', 'by', 'artist', 'album', 'name'] as const;
const FREE_KEYS = ['by', 'artist', 'album', 'name'] as const;

const query = ref(props.filters.q ?? '');

watch(
    () => props.filters.q,
    (value) => {
        query.value = value ?? '';
    },
);

const filtered = computed(() => query.value.trim() !== '');

const chips = computed<SearchChip[]>(() => {
    const counts = props.counts;
    const q = query.value;
    const result: SearchChip[] = [
        {key: 'sent:yes', label: 'Sent to Player', count: counts?.sent, pressed: hasToken(q, 'sent', 'yes')},
        {key: 'sent:no', label: 'Waiting', count: counts?.waiting, pressed: hasToken(q, 'sent', 'no')},
        {key: 'scheduled:yes', label: 'Scheduled', count: counts?.scheduled, pressed: hasToken(q, 'scheduled', 'yes')},
        {key: 'flagged:yes', label: 'Flagged', count: counts?.flagged, pressed: hasToken(q, 'flagged', 'yes')},
        {key: 'by:me', label: 'Mine', count: counts?.mine, pressed: hasToken(q, 'by', 'me')},
    ];
    const tokens = parseSearch(q, TOKEN_KEYS).tokens;
    for (const key of FREE_KEYS) {
        for (const value of tokens[key] ?? []) {
            if (key === 'by' && value === 'me') {
                continue;
            }
            result.push({
                key: `${key}:${value}`,
                label: `${key === 'by' ? 'By' : key[0].toUpperCase() + key.slice(1)}: ${value}`,
                pressed: true,
            });
        }
    }

    return result;
});

function visit(q: string): void {
    const next: Record<string, string> = {order: props.filters.order, order_direction: props.filters.order_direction};
    if (q.trim() !== '') {
        next.q = q.trim();
    }
    router.get(songsUrl, next);
}

function onSearch(value: string): void {
    query.value = value;
    visit(value);
}

const OPPOSITES: Record<string, string> = {'sent:yes': 'no', 'sent:no': 'yes'};

function toggleChip(chip: string): void {
    const [key, value] = [chip.slice(0, chip.indexOf(':')), chip.slice(chip.indexOf(':') + 1)];
    let next = query.value;
    if (chip in OPPOSITES && hasToken(next, key, OPPOSITES[chip])) {
        next = toggleToken(next, key, OPPOSITES[chip]);
    }
    query.value = serializeSearch(parseSearch(toggleToken(next, key, value), TOKEN_KEYS));
    visit(query.value);
}

function remove(song: SongRow): void {
    if (song.removeUrl) {
        router.delete(song.removeUrl);
    }
}

function initials(name: string): string {
    return name.slice(0, 2).toUpperCase();
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
        <div class="mp-page-head">
            <div>
                <h1>Requests</h1>
                <p>Everything requested at {{ party.name }}. Schedule or remove a Request from its menu.</p>
            </div>
        </div>

        <SearchBar
            :model-value="query"
            placeholder="Search tracks, artists, people… or try sent:no"
            label="Search Requests"
            :result-count="songs.total"
            :total="counts?.total"
            noun="Requests"
            :chips="chips"
            hint-lead="Words match every column. Add filters like"
            :hint-tokens="['sent:no', 'artist:abba', 'by:ravi']"
            hint-trail="; the chips set the same filters."
            @update:model-value="onSearch"
            @toggle-chip="toggleChip"
        />
        <div class="card">
            <ul
                v-if="songs.data.length > 0"
                class="list-group list-group-flush mp-rowlist d-md-none"
                data-test="songs-list"
            >
                <li v-for="song in songs.data" :key="song.id" class="list-group-item p-3" data-test="song-card">
                    <div class="mp-row">
                        <img
                            class="mp-art"
                            :src="song.imageUrl"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            height="56"
                            width="56"
                            :title="song.albumName"
                        />
                        <div class="mp-main">
                            <div class="mp-title fw-medium">
                                <Link :href="song.url">{{ song.name }}</Link>
                                <span v-if="song.flagged" class="badge bg-yellow-lt ms-1">Flagged</span>
                            </div>
                            <div class="text-secondary mp-line">{{ song.artists.join(', ') }}</div>
                            <div class="small text-secondary mt-1">
                                {{ song.requestedBy ? song.requestedBy.nickname : 'Unknown' }} &middot;
                                {{ song.createdAt.human }}
                            </div>
                            <div class="small mt-1 d-flex flex-wrap align-items-center gap-2">
                                <span v-if="song.queuedAt" class="text-secondary">
                                    <i class="icon ti ti-check me-1" aria-hidden="true"></i>Sent
                                    {{ song.queuedAt.human }}
                                </span>
                                <span v-else class="text-secondary">
                                    <i class="icon ti ti-hourglass me-1" aria-hidden="true"></i>Not yet sent
                                </span>
                                <span class="text-secondary mp-num">{{ song.votes }} votes</span>
                                <span v-if="song.scheduledLabel" class="badge bg-azure-lt"
                                    ><i class="icon ti ti-clock me-1" aria-hidden="true"></i>Scheduled
                                    {{ song.scheduledLabel }}</span
                                >
                            </div>
                        </div>
                        <RowMenu :label="`Actions for ${song.name}`">
                            <Link class="dropdown-item" :href="song.url"
                                ><i class="icon ti ti-eye me-2" aria-hidden="true"></i>Open Request</Link
                            >
                            <Link class="dropdown-item" :href="`${song.url}#scheduled-for`"
                                ><i class="icon ti ti-clock me-2" aria-hidden="true"></i>Schedule Request</Link
                            >
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item text-danger" @click="remove(song)">
                                <i class="icon ti ti-trash me-2" aria-hidden="true"></i>Remove Request
                                <small class="d-block text-secondary">Takes it out of the Queue</small>
                            </button>
                        </RowMenu>
                    </div>
                </li>
            </ul>
            <div v-if="songs.data.length > 0" class="d-none d-md-block">
                <table class="table table-hover table-vcenter card-table mp-tbl">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Requested by</th>
                            <th class="text-end">
                                <SortHeader
                                    title="Votes"
                                    field="votes"
                                    :order="filters.order"
                                    :order-direction="filters.order_direction"
                                />
                            </th>
                            <th class="text-end">
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
                            <th class="w-1"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="song in songs.data" :key="song.id" data-test="song-row">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        class="mp-art"
                                        :src="song.imageUrl"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        height="40"
                                        width="40"
                                        :title="song.albumName"
                                    />
                                    <div class="text-wrap">
                                        <div class="fw-medium">
                                            <Link :href="song.url">{{ song.name }}</Link>
                                            <span
                                                v-if="song.scheduledLabel"
                                                class="badge bg-azure-lt ms-1"
                                                data-test="scheduled-badge"
                                                ><i class="icon ti ti-clock me-1" aria-hidden="true"></i>Scheduled
                                                {{ song.scheduledLabel }}</span
                                            >
                                            <span
                                                v-if="song.flagged"
                                                class="badge bg-yellow-lt ms-1"
                                                data-test="flagged-badge"
                                                >Flagged</span
                                            >
                                        </div>
                                        <div class="text-secondary text-wrap">
                                            {{ song.artists.join(', ') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-test="requested-by">
                                <span v-if="song.requestedBy" class="mp-who">
                                    <span class="avatar avatar-sm rounded-circle">{{
                                        initials(song.requestedBy.nickname)
                                    }}</span>
                                    {{ song.requestedBy.nickname }}
                                    <span v-if="song.requestedBy.isYou" class="badge bg-blue-lt ms-1">You</span>
                                </span>
                                <span v-else class="text-secondary">Unknown</span>
                            </td>
                            <td class="text-end mp-num">{{ song.votes }}</td>
                            <td class="text-end mp-num">{{ song.score > 0 ? `+${song.score}` : song.score }}</td>
                            <td class="text-nowrap">
                                <span :title="song.createdAt.iso">{{ song.createdAt.human }}</span>
                            </td>
                            <td class="text-nowrap">
                                <span v-if="song.queuedAt" :title="song.queuedAt.iso">{{ song.queuedAt.human }}</span>
                                <span v-else class="text-secondary">Not yet</span>
                            </td>
                            <td class="text-end">
                                <RowMenu :label="`Actions for ${song.name}`">
                                    <Link class="dropdown-item" :href="song.url"
                                        ><i class="icon ti ti-eye me-2" aria-hidden="true"></i>Open Request</Link
                                    >
                                    <Link class="dropdown-item" :href="`${song.url}#scheduled-for`"
                                        ><i class="icon ti ti-clock me-2" aria-hidden="true"></i>Schedule Request</Link
                                    >
                                    <div class="dropdown-divider"></div>
                                    <button
                                        type="button"
                                        class="dropdown-item text-danger"
                                        data-test="remove-request"
                                        @click="remove(song)"
                                    >
                                        <i class="icon ti ti-trash me-2" aria-hidden="true"></i>Remove Request
                                        <small class="d-block text-secondary">Takes it out of the Queue</small>
                                    </button>
                                </RowMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="filtered"
                icon="ti-music-off"
                :title="`No Requests match ${query.trim()}`"
                :description="`Remove a filter to see more of the ${counts?.total ?? 0}.`"
            >
                <template #title
                    >No Requests match <code>{{ query.trim() }}</code></template
                >
                <Link :href="songsUrl" class="btn btn-primary">Clear filters</Link>
            </EmptyState>
            <EmptyState
                v-else
                icon="ti-music-off"
                title="No requests yet"
                description="Songs requested at this party will appear here."
            />
            <div v-if="songs.data.length > 0" class="card-footer d-flex align-items-center">
                <p class="m-0 text-secondary mp-num">
                    Showing <span>{{ songs.from ?? 0 }}</span> to <span>{{ songs.to ?? 0 }}</span> of
                    <span>{{ songs.total }}</span> Requests
                </p>
                <div class="m-0 ms-auto"><Pagination :paginator="songs" /></div>
            </div>
        </div>
    </AppLayout>
</template>

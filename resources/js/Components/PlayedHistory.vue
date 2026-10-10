<script setup>
import {Link, router} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import Icon from './Icon.vue';
import Decorations from './Decorations.vue';
import TrackThumb from './TrackThumb.vue';
import {formatPlayedAt} from '../lib/format';
import {useRatePlay} from '../lib/rating';

const props = defineProps({
    history: {type: Object, default: null},
    filters: {type: Object, default: () => ({})},
    partyCode: {type: String, default: ''},
    readOnly: {type: Boolean, default: false},
});

const {error: ratingError, rate} = useRatePlay(() => props.partyCode, () => props.readOnly);
const rateClass = (active, activeColor) => [
    'flex h-8 w-8 items-center justify-center rounded hover:bg-border/40 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
    active ? activeColor : 'text-muted',
];

const typeOptions = [
    {value: 'sent', label: 'Sent to Spotify'},
    {value: 'requested', label: 'Requested songs'},
    {value: 'fallback', label: 'Fallback playlist'},
];

const rows = computed(() => props.history?.data ?? []);
const meta = computed(() => props.history?.meta ?? {});
const first = computed(() => meta.value.from ?? 0);
const last = computed(() => meta.value.to ?? 0);
const total = computed(() => meta.value.total ?? rows.value.length);
const pageLinks = computed(() => {
    const links = meta.value.links ?? [];
    return links.map((link, index) => ({
        key: index,
        url: link.url,
        active: link.active,
        label: index === 0 ? '‹' : index === links.length - 1 ? '›' : link.label,
        kind: index === 0 ? 'prev' : index === links.length - 1 ? 'next' : 'page',
    }));
});
const showPager = computed(() => pageLinks.value.length > 3);

const form = reactive({
    name: props.filters.name ?? '',
    artist: props.filters.artist ?? '',
    album: props.filters.album ?? '',
    type: props.filters.type || 'sent',
});
const filtersActive = computed(() => Boolean(form.name || form.artist || form.album || form.type !== 'sent'));
const filtersOpen = ref(filtersActive.value);

const search = () => {
    const params = Object.fromEntries(
        Object.entries(form).filter(([key, value]) => value.trim() !== '' && !(key === 'type' && value === 'sent')),
    );
    router.get(`/parties/${props.partyCode}/history`, params, {preserveState: true, preserveScroll: true, replace: true});
};

const inputClass = 'h-10 w-full rounded border border-border bg-surface px-3 text-sm text-text placeholder:text-muted/70 focus:border-primary focus:outline-none dark:bg-background';
const labelClass = 'mb-1.5 block text-sm font-medium';
const headClass = 'px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-primary';
const cellClass = 'md:table-cell md:px-5 md:py-3 md:align-middle before:block before:text-[11px] before:font-bold before:uppercase before:tracking-wide before:text-primary before:content-[attr(data-label)] md:before:hidden';
</script>

<template>
    <div class="grid gap-4 md:grid-cols-[17.5rem_minmax(0,1fr)] md:items-start" data-testid="history-layout">
        <section class="rounded border border-border bg-surface" aria-label="Search history">
            <button
                type="button"
                data-testid="history-filters-toggle"
                :aria-expanded="filtersOpen"
                class="flex min-h-11 w-full items-center justify-between px-4 text-left text-base font-medium md:hidden"
                @click="filtersOpen = !filtersOpen"
            >
                <span>Search</span>
                <Icon :name="filtersOpen ? 'arrowUp' : 'arrowDown'" />
            </button>
            <h3 class="hidden border-b border-border px-5 py-4 text-base font-medium md:block">Search</h3>
            <form :class="[filtersOpen ? 'block' : 'hidden', 'md:block']" data-testid="history-filters" @submit.prevent="search">
                <div class="space-y-4 border-t border-border px-4 py-4 md:border-t-0 md:px-5 md:py-5">
                    <div>
                        <label for="history-name" :class="labelClass">Name</label>
                        <input id="history-name" v-model="form.name" data-testid="filter-name" type="text" maxlength="100" placeholder="Name" :class="inputClass" />
                    </div>
                    <div>
                        <label for="history-artist" :class="labelClass">Artist</label>
                        <input id="history-artist" v-model="form.artist" data-testid="filter-artist" type="text" maxlength="100" placeholder="Artist" :class="inputClass" />
                    </div>
                    <div>
                        <label for="history-album" :class="labelClass">Album</label>
                        <input id="history-album" v-model="form.album" data-testid="filter-album" type="text" maxlength="100" placeholder="Album" :class="inputClass" />
                    </div>
                    <div>
                        <label for="history-type" :class="labelClass">Type</label>
                        <select id="history-type" v-model="form.type" data-testid="filter-type" :class="inputClass">
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end border-t border-border px-4 py-4 md:px-5">
                    <button
                        type="submit"
                        data-testid="filter-submit"
                        class="flex h-11 w-full items-center justify-center rounded bg-primary px-4 text-sm font-medium text-white hover:opacity-90 md:h-10 md:w-auto"
                    >Search</button>
                </div>
            </form>
        </section>

        <section class="min-w-0 rounded border border-border bg-surface" aria-label="Played songs">
            <p v-if="ratingError" role="alert" data-testid="history-rating-error" class="px-5 pt-4 text-sm text-danger">{{ ratingError }}</p>
            <p v-if="rows.length === 0" data-testid="history-empty" class="px-5 py-6 text-sm text-muted">
                {{ filtersActive ? 'No songs match this search.' : 'Nothing has been played yet.' }}
            </p>
            <table v-else data-testid="history-list" class="block w-full text-sm md:table">
                <thead class="hidden md:table-header-group">
                    <tr class="border-b border-border">
                        <th scope="col" :class="[headClass, 'text-left !text-muted']">Song</th>
                        <th scope="col" :class="[headClass, 'text-center']">Votes</th>
                        <th scope="col" :class="[headClass, 'text-center']">Score</th>
                        <th scope="col" :class="[headClass, 'text-left']">
                            <span class="inline-flex items-center gap-1">Requested <Icon name="arrowUp" class="h-3 w-3" aria-hidden="true" /></span>
                        </th>
                        <th scope="col" :class="[headClass, 'text-left']">Sent to Spotify</th>
                    </tr>
                </thead>
                <tbody class="block md:table-row-group">
                    <tr
                        v-for="play in rows"
                        :key="play.id"
                        data-testid="history-item"
                        class="grid grid-cols-2 gap-x-4 gap-y-3 border-b border-border px-4 py-4 md:table-row md:px-0 md:py-0"
                    >
                        <td data-label="Song" :class="[cellClass, 'col-span-2 !block before:!hidden md:!table-cell']">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 md:flex-nowrap">
                                <TrackThumb :src="play.track.artwork_url" class="!h-10 !w-10 md:!h-10 md:!w-10" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-primary" data-testid="history-title">{{ play.track.title }}</div>
                                    <div class="truncate text-muted">{{ play.track.artists.join(', ') }}</div>
                                    <Decorations :decorations="play.decorations" class="mt-1" />
                                </div>
                                <div data-testid="history-rating" class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        data-testid="rate-dislike"
                                        :aria-label="`Dislike ${play.track.title}`"
                                        :aria-pressed="play.my_rating === -1"
                                        :disabled="readOnly"
                                        :class="rateClass(play.my_rating === -1, 'text-danger')"
                                        @click="rate(play, 'down')"
                                    ><Icon name="thumbDown" class="h-4 w-4" /></button>
                                    <button
                                        type="button"
                                        data-testid="rate-like"
                                        :aria-label="`Like ${play.track.title}`"
                                        :aria-pressed="play.my_rating === 1"
                                        :disabled="readOnly"
                                        :class="rateClass(play.my_rating === 1, 'text-accent')"
                                        @click="rate(play, 'up')"
                                    ><Icon name="thumbUp" class="h-4 w-4" /></button>
                                </div>
                            </div>
                        </td>
                        <td data-label="Votes" data-testid="history-votes" :class="[cellClass, 'md:text-center']">{{ play.votes }}</td>
                        <td data-label="Score" data-testid="history-score" :class="[cellClass, 'md:text-center']">{{ play.score }}</td>
                        <td data-label="Requested" data-testid="history-requested" :class="cellClass">
                            <time :datetime="play.requested_at">{{ formatPlayedAt(play.requested_at) }}</time>
                        </td>
                        <td data-label="Sent to Spotify" :class="cellClass">
                            <time data-testid="history-played-at" :datetime="play.played_at">{{ formatPlayedAt(play.played_at) }}</time>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="flex flex-col gap-3 px-4 py-4 text-sm text-muted md:flex-row md:items-center md:justify-between md:px-5">
                <p data-testid="history-entries">Showing {{ first }} to {{ last }} of {{ total }} entries</p>
                <nav v-if="showPager" aria-label="Pagination" data-testid="history-pager" class="flex flex-wrap items-center gap-1">
                    <template v-for="link in pageLinks" :key="link.key">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :data-testid="`history-${link.kind}`"
                            :aria-current="link.active ? 'page' : null"
                            :aria-label="link.kind === 'prev' ? 'Previous page' : link.kind === 'next' ? 'Next page' : `Page ${link.label}`"
                            :class="[
                                'flex h-9 min-w-9 items-center justify-center rounded border px-2 text-sm',
                                link.active ? 'border-primary bg-primary text-white' : 'border-border text-text hover:text-primary',
                            ]"
                        >{{ link.label }}</Link>
                        <span
                            v-else
                            :data-testid="`history-${link.kind}-disabled`"
                            class="flex h-9 min-w-9 items-center justify-center rounded border border-border px-2 text-sm opacity-50"
                        >{{ link.label }}</span>
                    </template>
                </nav>
            </div>
        </section>
    </div>
</template>

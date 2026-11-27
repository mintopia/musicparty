<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {aiReviewsUrl, aiReviewUrl, outcomeBadge} from '@/lib/aiReviews';
import type {AiReviewRow, LegacyPartyState, ModerationTabsProps, Paginator} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    filters: {outcome: string; criterion: string; q: string};
    outcomes: {code: string; name: string}[];
    criteria: {code: string; name: string}[];
    holdsFlagged: boolean;
    moderationTabs: ModerationTabsProps;
    settingsUrl: string;
    counts: {total: number; outcome: Record<string, number>; criterion: Record<string, number>};
    reviews: Paginator<AiReviewRow> & {from: number | null; to: number | null; total: number};
}>();

const form = reactive({q: props.filters.q, outcome: props.filters.outcome, criterion: props.filters.criterion});
const listUrl = aiReviewsUrl(props.party.code);
const filtered = computed(
    () => props.filters.q !== '' || props.filters.outcome !== '' || props.filters.criterion !== '',
);

const chips = computed<SearchChip[]>(() => [
    ...props.outcomes.map((outcome) => ({
        key: `outcome:${outcome.code}`,
        label: outcome.name,
        count: props.counts.outcome[outcome.code] ?? 0,
        pressed: form.outcome === outcome.code,
    })),
    ...props.criteria.map((criterion) => ({
        key: `criterion:${criterion.code}`,
        label: criterion.name,
        count: props.counts.criterion[criterion.code] ?? 0,
        pressed: form.criterion === criterion.code,
    })),
]);

const activeSummary = computed(() => {
    const parts: string[] = [];
    if (props.filters.q !== '') {
        parts.push(`\u201c${props.filters.q}\u201d`);
    }
    const outcome = props.outcomes.find((item) => item.code === props.filters.outcome);
    if (outcome) {
        parts.push(`outcome ${outcome.name}`);
    }
    const criterion = props.criteria.find((item) => item.code === props.filters.criterion);
    if (criterion) {
        parts.push(`criterion ${criterion.name}`);
    }
    return parts.join(', ');
});

function apply(): void {
    const query: Record<string, string> = {};
    if (form.q.trim() !== '') {
        query.q = form.q.trim();
    }
    if (form.outcome !== '') {
        query.outcome = form.outcome;
    }
    if (form.criterion !== '') {
        query.criterion = form.criterion;
    }
    router.get(listUrl, query, {preserveState: true, replace: true});
}

function setQuery(value: string): void {
    form.q = value;
    apply();
}

function toggleChip(key: string): void {
    const [field, code] = key.split(':') as ['outcome' | 'criterion', string];
    form[field] = form[field] === code ? '' : code;
    apply();
}

const showUrl = (review: AiReviewRow): string =>
    aiReviewUrl(props.party.code, review.id, {
        q: props.filters.q,
        outcome: props.filters.outcome,
        criterion: props.filters.criterion,
        page: props.reviews.current_page,
    });
</script>

<template>
    <AppLayout :title="`AI reviews - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'AI Reviews'}]" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`AI reviews - ${party.name}`" />
        <h1 class="visually-hidden">AI reviews</h1>
        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="ai-reviews" />

        <div v-if="!holdsFlagged" class="alert alert-warning py-2" role="status" data-test="not-held-notice">
            <i class="icon ti ti-flag me-2" aria-hidden="true"></i>
            Flagged Requests aren't held. They go straight to the Queue.
            <a :href="settingsUrl" class="ms-1" data-test="turn-on-approval">Turn on approval</a>
        </div>

        <SearchBar
            :model-value="form.q"
            placeholder="Search tracks, artists, people"
            label="Search AI reviews"
            :result-count="reviews.total"
            :total="counts.total"
            noun="reviews"
            :chips="chips"
            @update:model-value="setQuery"
            @submit="setQuery"
            @toggle-chip="toggleChip"
        />
        <div>
            <div>
                <EmptyState
                    v-if="reviews.data.length === 0 && filtered"
                    icon="ti-filter-off"
                    title="No matching reviews"
                    :description="`Nothing matches ${activeSummary}.`"
                >
                    <Link :href="listUrl" class="btn btn-primary" data-test="clear-filters">Clear filters</Link>
                </EmptyState>
                <EmptyState
                    v-else-if="reviews.data.length === 0"
                    icon="ti-robot"
                    title="No AI reviews yet"
                    description="Each requested track is reviewed and recorded here."
                />
                <div v-else class="card">
                    <ul class="list-group list-group-flush d-md-none" data-test="reviews-list">
                        <li v-for="review in reviews.data" :key="review.id" class="list-group-item p-0">
                            <Link :href="showUrl(review)" class="d-block p-3 text-reset" data-test="review-item">
                                <div class="fw-medium text-break">{{ review.track.title }}</div>
                                <div class="text-secondary small text-break">{{ review.track.artists }}</div>
                                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                    <span class="badge" :class="outcomeBadge[review.outcome].class">
                                        <i
                                            class="icon ti me-1"
                                            :class="outcomeBadge[review.outcome].icon"
                                            aria-hidden="true"
                                        ></i>
                                        {{ review.outcomeLabel }}
                                    </span>
                                    <span v-if="review.criterionLabel" class="text-secondary small">{{
                                        review.criterionLabel
                                    }}</span>
                                </div>
                                <div v-if="review.reason" class="reason-text mt-2">{{ review.reason }}</div>
                                <div class="text-secondary small mt-2">
                                    {{ review.user ?? 'Unknown' }}
                                    <template v-if="review.createdAt"> &middot; {{ review.createdAt.human }}</template>
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover table-vcenter card-table" data-test="reviews-table">
                            <thead>
                                <tr>
                                    <th>Outcome</th>
                                    <th>Criterion</th>
                                    <th>Track</th>
                                    <th>Requested by</th>
                                    <th>Reason</th>
                                    <th class="w-1"><span class="visually-hidden">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="review in reviews.data" :key="review.id">
                                    <tr data-test="review-row">
                                        <td class="text-nowrap">
                                            <span
                                                class="badge"
                                                :class="outcomeBadge[review.outcome].class"
                                                data-test="review-outcome"
                                            >
                                                <i class="icon ti me-1" :class="outcomeBadge[review.outcome].icon"></i>
                                                {{ review.outcomeLabel }}
                                            </span>
                                            <span
                                                v-if="review.outcome === 'flag' && !holdsFlagged"
                                                class="text-secondary small ms-1"
                                                data-test="not-held"
                                                >not held</span
                                            >
                                        </td>
                                        <td class="text-nowrap" data-test="review-criterion">
                                            <span v-if="review.criterionLabel">{{ review.criterionLabel }}</span>
                                            <span v-else class="text-secondary">-</span>
                                        </td>
                                        <td class="track-col">
                                            <div class="fw-medium" data-test="review-title">
                                                <Link
                                                    :href="showUrl(review)"
                                                    class="text-reset"
                                                    data-test="review-link"
                                                    >{{ review.track.title }}</Link
                                                >
                                            </div>
                                            <div class="text-secondary small">{{ review.track.artists }}</div>
                                        </td>
                                        <td class="text-nowrap" data-test="review-user">
                                            {{ review.user ?? 'Unknown' }}
                                            <div
                                                v-if="review.createdAt"
                                                class="text-secondary small"
                                                :title="review.createdAt.iso"
                                                data-test="review-when"
                                            >
                                                {{ review.createdAt.human }}
                                            </div>
                                        </td>
                                        <td class="reason-cell" data-test="review-reason">
                                            <span v-if="review.reason" class="reason-text" :title="review.reason">{{
                                                review.reason
                                            }}</span>
                                            <span v-else class="text-secondary">None</span>
                                        </td>
                                        <td class="text-end">
                                            <Link
                                                :href="showUrl(review)"
                                                class="btn btn-icon btn-sm btn-ghost-secondary"
                                                aria-label="Open review"
                                                data-test="open-review"
                                            >
                                                <i class="icon ti ti-chevron-right" aria-hidden="true"></i>
                                            </Link>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex align-items-center">
                        <p class="m-0 text-secondary tabular-nums">
                            {{ reviews.from ?? 0 }}-{{ reviews.to ?? 0 }} of {{ reviews.total }}
                        </p>
                        <div class="m-0 ms-auto"><Pagination :paginator="reviews" /></div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.track-col {
    min-width: 12rem;
    max-width: 16rem;
}

.reason-text {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    overflow-wrap: anywhere;
}
</style>

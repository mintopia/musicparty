<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';
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
    reviews: Paginator<AiReviewRow> & {from: number | null; to: number | null; total: number};
}>();

const form = reactive({q: props.filters.q, outcome: props.filters.outcome, criterion: props.filters.criterion});
const listUrl = aiReviewsUrl(props.party.code);
const filtered = computed(
    () => props.filters.q !== '' || props.filters.outcome !== '' || props.filters.criterion !== '',
);

const filtersOpen = ref(filtered.value);

function submit(): void {
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
    router.get(listUrl, query);
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
        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="ai-reviews" />

        <div v-if="!holdsFlagged" class="alert alert-warning py-2" role="status" data-test="not-held-notice">
            <i class="icon ti ti-flag me-2" aria-hidden="true"></i>
            Flagged Requests aren't held. They go straight to the Queue.
            <a :href="settingsUrl" class="ms-1" data-test="turn-on-approval">Turn on approval</a>
        </div>

        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form :action="listUrl" method="get" class="card" data-test="filters" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost-secondary ms-auto d-lg-none"
                            :aria-expanded="filtersOpen"
                            aria-controls="ai-review-filter-fields"
                            data-test="toggle-filters"
                            @click="filtersOpen = !filtersOpen"
                        >
                            {{ filtersOpen ? 'Hide' : 'Show' }}
                            <i
                                class="icon ti ms-1"
                                :class="filtersOpen ? 'ti-chevron-up' : 'ti-chevron-down'"
                                aria-hidden="true"
                            ></i>
                        </button>
                    </div>
                    <div id="ai-review-filter-fields" class="card-body d-lg-block" :class="{'d-none': !filtersOpen}">
                        <SearchTextField v-model="form.q" name="Track, artist or user" property="q" />
                        <SearchSelectField
                            v-model="form.outcome"
                            name="Outcome"
                            property="outcome"
                            :options="outcomes"
                            value-key="code"
                            label-key="name"
                        />
                        <SearchSelectField
                            v-model="form.criterion"
                            name="Criterion"
                            property="criterion"
                            :options="criteria"
                            value-key="code"
                            label-key="name"
                        />
                    </div>
                    <div class="card-footer d-lg-flex" :class="filtersOpen ? 'd-flex' : 'd-none'">
                        <Link v-if="filtered" :href="listUrl" class="btn btn-link ms-auto" data-test="clear-filters"
                            >Clear</Link
                        >
                        <button class="btn btn-primary" :class="{'ms-auto': !filtered}" type="submit">Search</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-9 col-md-12">
                <EmptyState
                    v-if="reviews.data.length === 0 && filtered"
                    icon="ti-filter-off"
                    title="No matching reviews"
                    description="Try a different search or outcome."
                >
                    <Link :href="listUrl" class="btn btn-primary">Clear filters</Link>
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

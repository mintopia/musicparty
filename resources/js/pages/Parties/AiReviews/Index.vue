<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onMounted, reactive, ref} from 'vue';
import AiReviewDetailPanel from '@/components/AiReviewDetail.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {aiReviewsUrl, aiReviewUrl, formatLatency, formatTokens, outcomeBadge} from '@/lib/aiReviews';
import type {AiReviewDetail, AiReviewRow, LegacyPartyState, ModerationTabsProps, Paginator} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    filters: {outcome: string; q: string; review: number | null};
    outcomes: {code: string; name: string}[];
    holdsFlagged: boolean;
    moderationTabs: ModerationTabsProps;
    settingsUrl: string;
    reviews: Paginator<AiReviewRow> & {from: number | null; to: number | null; total: number};
}>();

const form = reactive({q: props.filters.q, outcome: props.filters.outcome});
const listUrl = aiReviewsUrl(props.party.code);
const filtered = computed(
    () => props.filters.q !== '' || props.filters.outcome !== '' || props.filters.review !== null,
);
const columns = 8;

const expanded = ref<number | null>(null);
const details = reactive<Record<number, AiReviewDetail>>({});
const loading = ref<number | null>(null);
const failed = ref<number | null>(null);

function submit(): void {
    const query: Record<string, string> = {};
    if (form.q.trim() !== '') {
        query.q = form.q.trim();
    }
    if (form.outcome !== '') {
        query.outcome = form.outcome;
    }
    router.get(listUrl, query);
}

async function load(review: AiReviewRow): Promise<void> {
    failed.value = null;
    loading.value = review.id;
    try {
        const {data} = await axios.get<{data: AiReviewDetail}>(aiReviewUrl(props.party.code, review.id));
        details[review.id] = data.data;
    } catch {
        failed.value = review.id;
    } finally {
        loading.value = null;
    }
}

onMounted(() => {
    const focused = props.reviews.data.find((review) => review.id === props.filters.review);
    if (focused) {
        void toggle(focused);
    }
});

async function toggle(review: AiReviewRow): Promise<void> {
    if (expanded.value === review.id) {
        expanded.value = null;
        return;
    }
    expanded.value = review.id;
    if (!details[review.id]) {
        await load(review);
    }
}
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
            Flagged requests aren't held. They go straight to the queue.
            <a :href="settingsUrl" class="ms-1" data-test="turn-on-approval">Turn on approval</a>
        </div>

        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form :action="listUrl" method="get" class="card" data-test="filters" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                    </div>
                    <div class="card-body">
                        <SearchTextField v-model="form.q" name="Track, artist or user" property="q" />
                        <SearchSelectField
                            v-model="form.outcome"
                            name="Outcome"
                            property="outcome"
                            :options="outcomes"
                            value-key="code"
                            label-key="name"
                        />
                    </div>
                    <div class="card-footer d-flex">
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
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter card-table" data-test="reviews-table">
                            <thead>
                                <tr>
                                    <th>Outcome</th>
                                    <th>Track</th>
                                    <th>Requested by</th>
                                    <th class="reason-col">Reason</th>
                                    <th class="text-end">Latency</th>
                                    <th class="text-end">Tokens</th>
                                    <th class="text-end">When</th>
                                    <th class="w-1"><span class="visually-hidden">Details</span></th>
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
                                        <td>
                                            <div class="fw-medium" data-test="review-title">
                                                {{ review.track.title }}
                                            </div>
                                            <div class="text-secondary small">{{ review.track.artists }}</div>
                                        </td>
                                        <td class="text-nowrap" data-test="review-user">
                                            {{ review.user ?? 'Unknown' }}
                                        </td>
                                        <td class="reason-col" data-test="review-reason">
                                            <span
                                                v-if="review.reason"
                                                class="d-block text-truncate"
                                                :title="review.reason"
                                                >{{ review.reason }}</span
                                            >
                                            <span v-else class="text-secondary">None</span>
                                        </td>
                                        <td class="text-end text-nowrap tabular-nums">
                                            {{ formatLatency(review.latencyMs) }}
                                        </td>
                                        <td class="text-end text-nowrap tabular-nums">
                                            {{ formatTokens(review.totalTokens) }}
                                        </td>
                                        <td class="text-end text-nowrap text-secondary">
                                            <span v-if="review.createdAt" :title="review.createdAt.iso">{{
                                                review.createdAt.human
                                            }}</span>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-icon btn-sm btn-ghost-secondary"
                                                :aria-expanded="expanded === review.id"
                                                :aria-controls="`ai-review-detail-${review.id}`"
                                                :aria-label="expanded === review.id ? 'Hide details' : 'Show details'"
                                                data-test="toggle-detail"
                                                @click="toggle(review)"
                                            >
                                                <i
                                                    class="icon ti"
                                                    :class="
                                                        expanded === review.id ? 'ti-chevron-up' : 'ti-chevron-down'
                                                    "
                                                ></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="expanded === review.id" data-test="detail-row">
                                        <td
                                            :id="`ai-review-detail-${review.id}`"
                                            :colspan="columns"
                                            class="bg-body-tertiary"
                                        >
                                            <div
                                                v-if="loading === review.id"
                                                class="text-secondary py-3"
                                                data-test="detail-loading"
                                            >
                                                <span
                                                    class="spinner-border spinner-border-sm me-2"
                                                    aria-hidden="true"
                                                ></span>
                                                Loading details
                                            </div>
                                            <div
                                                v-else-if="failed === review.id"
                                                class="alert alert-danger mb-0"
                                                role="alert"
                                                data-test="detail-failed"
                                            >
                                                The details could not be loaded.
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger ms-2"
                                                    @click="load(review)"
                                                >
                                                    Try again
                                                </button>
                                            </div>
                                            <AiReviewDetailPanel
                                                v-else-if="details[review.id]"
                                                :review="details[review.id]!"
                                            />
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
.reason-col {
    width: 30%;
    max-width: 0;
}
</style>

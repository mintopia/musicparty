<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, reactive, ref} from 'vue';
import AiReviewDetailPanel from '@/components/AiReviewDetail.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {aiReviewsUrl, aiReviewUrl, formatLatency, formatTokens, outcomeBadge} from '@/lib/aiReviews';
import {home} from '@/routes';
import {show} from '@/routes/parties';
import type {AiReviewDetail, AiReviewRow, LegacyPartyState, Paginator} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    filters: {outcome: string; q: string};
    outcomes: {code: string; name: string}[];
    retentionDays: number;
    reviews: Paginator<AiReviewRow> & {from: number | null; to: number | null; total: number};
}>();

const form = reactive({q: props.filters.q, outcome: props.filters.outcome});
const listUrl = aiReviewsUrl(props.party.code);
const filtered = computed(() => props.filters.q !== '' || props.filters.outcome !== '');
const columns = 9;

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
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <a :href="show(party.code).url">{{ party.name }}</a>
            </li>
            <li class="breadcrumb-item active">
                <a :href="listUrl">AI Reviews</a>
            </li>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`AI reviews - ${party.name}`" />
        <div class="page-header mt-0">
            <h1>AI reviews</h1>
            <p class="text-secondary mb-0">
                What the AI Request Review decided for each requested track. Kept for {{ retentionDays }} days.
            </p>
        </div>

        <form class="card mb-3" role="search" data-test="filters" @submit.prevent="submit">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label" for="ai-review-q">Track, artist or user</label>
                        <input
                            id="ai-review-q"
                            v-model="form.q"
                            class="form-control"
                            type="search"
                            name="q"
                            maxlength="100"
                            autocomplete="off"
                        />
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label" for="ai-review-outcome">Outcome</label>
                        <select id="ai-review-outcome" v-model="form.outcome" class="form-select" name="outcome">
                            <option value="">All outcomes</option>
                            <option v-for="outcome in outcomes" :key="outcome.code" :value="outcome.code">
                                {{ outcome.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-lg-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <Link v-if="filtered" :href="listUrl" class="btn btn-link" data-test="clear-filters">
                            Clear filters
                        </Link>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <EmptyState
                v-if="reviews.data.length === 0 && filtered"
                icon="ti-filter-off"
                title="No reviews match these filters"
                description="Try a different track, artist or user, or choose another outcome."
            >
                <Link :href="listUrl" class="btn btn-primary">Clear filters</Link>
            </EmptyState>
            <EmptyState
                v-else-if="reviews.data.length === 0"
                icon="ti-robot"
                title="No AI reviews yet"
                description="Each time someone requests a track, the AI review is recorded here with what was sent and what came back."
            />
            <template v-else>
                <div class="table-responsive">
                    <table class="table table-hover table-vcenter card-table" data-test="reviews-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Requested by</th>
                                <th>Track</th>
                                <th>Outcome</th>
                                <th>Reason</th>
                                <th>Driver and model</th>
                                <th class="text-end">Latency</th>
                                <th class="text-end">Tokens</th>
                                <th><span class="visually-hidden">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="review in reviews.data" :key="review.id">
                                <tr data-test="review-row">
                                    <td class="text-nowrap">
                                        <span v-if="review.createdAt" :title="review.createdAt.iso">{{
                                            review.createdAt.human
                                        }}</span>
                                    </td>
                                    <td data-test="review-user">
                                        {{ review.user ?? 'Unknown' }}
                                    </td>
                                    <td>
                                        <div class="fw-medium" data-test="review-title">{{ review.track.title }}</div>
                                        <div class="text-secondary">{{ review.track.artists }}</div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="outcomeBadge[review.outcome].class"
                                            data-test="review-outcome"
                                        >
                                            <i class="icon ti me-1" :class="outcomeBadge[review.outcome].icon"></i>
                                            {{ review.outcomeLabel }}
                                        </span>
                                    </td>
                                    <td data-test="review-reason">
                                        <template v-if="review.reason">{{ review.reason }}</template>
                                        <span v-else class="text-secondary">None</span>
                                    </td>
                                    <td>
                                        <div>{{ review.driver }}</div>
                                        <div class="text-secondary text-break">{{ review.model ?? '-' }}</div>
                                    </td>
                                    <td class="text-end text-nowrap tabular-nums">
                                        {{ formatLatency(review.latencyMs) }}
                                    </td>
                                    <td class="text-end text-nowrap tabular-nums">
                                        {{ formatTokens(review.totalTokens) }}
                                    </td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm"
                                            :aria-expanded="expanded === review.id"
                                            :aria-controls="`ai-review-detail-${review.id}`"
                                            data-test="toggle-detail"
                                            @click="toggle(review)"
                                        >
                                            {{ expanded === review.id ? 'Hide details' : 'Details' }}
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
                    <p class="m-0 text-secondary">
                        Showing {{ reviews.from ?? 0 }} to {{ reviews.to ?? 0 }} of {{ reviews.total }} reviews
                    </p>
                    <div class="m-0 ms-auto"><Pagination :paginator="reviews" /></div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

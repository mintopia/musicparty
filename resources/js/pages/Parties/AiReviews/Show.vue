<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import {computed} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CodeBlock from '@/components/CodeBlock.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {
    aiReviewsUrl,
    answerRows,
    formatLatency,
    formatPercent,
    formatTokens,
    outcomeBadge,
    prettyJson,
    requestBlocks,
} from '@/lib/aiReviews';
import type {AiReviewDetail, LegacyPartyState, ModerationTabsProps} from '@/types';

const props = defineProps<{
    party: {code: string; name: string};
    playerState: LegacyPartyState;
    moderationTabs: ModerationTabsProps;
    backUrl: string;
    criterionLabels: Record<string, string>;
    review: AiReviewDetail;
}>();

const listUrl = computed(() => props.backUrl || aiReviewsUrl(props.party.code));
const badge = computed(() => outcomeBadge[props.review.outcome]);
const answers = computed(() => answerRows(props.review.response, props.criterionLabels));
const criterionBreakdown = computed(
    () => answers.value.find((answer) => answer.id === 'criterion')?.probabilities ?? [],
);
const requestParts = computed(() => requestBlocks(props.review.request));
const tokens = computed(
    () =>
        `${formatTokens(props.review.promptTokens)} in, ${formatTokens(props.review.completionTokens)} out, ${formatTokens(props.review.totalTokens)} total`,
);
</script>

<template>
    <AppLayout :title="`${review.track.title} - AI review - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...partyCrumbs(party), {label: 'AI Reviews', href: listUrl}, {label: review.track.title}]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`${review.track.title} - AI review - ${party.name}`" />
        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="ai-reviews" />

        <div class="mb-3">
            <Link :href="listUrl" class="text-secondary" data-test="back-link">
                <i class="icon ti ti-arrow-left me-1" aria-hidden="true"></i>All AI reviews
            </Link>
        </div>

        <div class="d-flex flex-wrap align-items-start gap-3 mb-4">
            <div class="me-auto">
                <h2 class="page-title text-break" data-test="review-title">{{ review.track.title }}</h2>
                <div class="text-secondary" data-test="review-artists">{{ review.track.artists }}</div>
            </div>
            <div class="text-secondary text-md-end">
                <div data-test="review-user">Requested by {{ review.user ?? 'Unknown' }}</div>
                <div v-if="review.createdAt" :title="review.createdAt.iso" data-test="review-when">
                    {{ review.createdAt.human }}
                </div>
            </div>
        </div>

        <div class="card mb-4" data-test="verdict">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <span class="badge fs-4 py-2 px-3" :class="badge.class" data-test="review-outcome">
                    <i class="icon ti me-2" :class="badge.icon" aria-hidden="true"></i>{{ review.outcomeLabel }}
                </span>
                <p class="m-0 fs-3 flex-fill text-break" data-test="detail-reason">
                    {{ review.reason ?? 'No reason given' }}
                </p>
            </div>
            <div v-if="review.criterionLabel" class="card-footer" data-test="failing-criterion">
                <span class="text-secondary me-2">Failing criterion</span>
                <strong class="fs-4">{{ review.criterionLabel }}</strong>
                <span v-if="review.criterionConfidence !== null" class="text-secondary tabular-nums ms-2"
                    >{{ Math.round(review.criterionConfidence * 100) }}% confident</span
                >
            </div>
            <div v-if="criterionBreakdown.length > 0" class="card-body border-top" data-test="criterion-breakdown">
                <h3 class="fs-5 mb-3">Criterion probabilities</h3>
                <div
                    v-for="p in criterionBreakdown"
                    :key="p.key"
                    class="row align-items-center g-2 mb-2"
                    data-test="criterion-probability"
                >
                    <div class="col-4 col-md-2 text-break">{{ p.label }}</div>
                    <div class="col">
                        <div class="progress" role="presentation">
                            <div class="progress-bar" :style="{width: `${p.percent}%`}"></div>
                        </div>
                    </div>
                    <div class="col-auto tabular-nums text-secondary" style="min-width: 3.5rem">{{ p.percent }}%</div>
                </div>
            </div>
        </div>

        <div v-if="review.errorClass" class="alert alert-warning" role="alert" data-test="detail-error">
            <div class="fw-bold text-break">{{ review.errorClass }}</div>
            <div class="text-break">{{ review.errorMessage }}</div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Review</h3></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5 text-secondary fw-normal">Model</dt>
                            <dd class="col-7 text-break" data-test="detail-model">
                                {{ review.model ?? '-' }} ({{ review.driver }})
                            </dd>
                            <dt class="col-5 text-secondary fw-normal">Endpoint</dt>
                            <dd class="col-7 text-break" data-test="detail-endpoint">
                                {{ review.endpointHost ?? '-' }}
                            </dd>
                            <dt class="col-5 text-secondary fw-normal">Score</dt>
                            <dd class="col-7 tabular-nums">{{ review.score ?? '-' }}</dd>
                            <dt class="col-5 text-secondary fw-normal">Confidence</dt>
                            <dd class="col-7 tabular-nums">
                                {{ review.confidence === null ? '-' : formatPercent(review.confidence) }}
                            </dd>
                            <dt class="col-5 text-secondary fw-normal">Latency</dt>
                            <dd class="col-7 tabular-nums" data-test="detail-latency">
                                {{ formatLatency(review.latencyMs) }}
                            </dd>
                            <dt class="col-5 text-secondary fw-normal">Tokens</dt>
                            <dd class="col-7 tabular-nums" data-test="detail-tokens">{{ tokens }}</dd>
                            <dt class="col-5 text-secondary fw-normal">Queued</dt>
                            <dd class="col-7 mb-0">{{ review.requested ? 'Yes' : 'No' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100" data-test="answers">
                    <div class="card-header"><h3 class="card-title">Model answers</h3></div>
                    <div v-if="answers.length > 0" class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Answer</th>
                                    <th class="text-end">Confidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="answer in answers" :key="answer.id" data-test="answer-row">
                                    <td class="text-break">{{ answer.id }}</td>
                                    <td class="tabular-nums text-break">
                                        {{ answer.value }}
                                        <div
                                            v-if="answer.probabilities.length > 0"
                                            class="small text-secondary mt-1"
                                            data-test="answer-probabilities"
                                        >
                                            <span
                                                v-for="p in answer.probabilities"
                                                :key="p.key"
                                                class="me-3 text-nowrap"
                                                >{{ p.label }} {{ p.percent }}%</span
                                            >
                                        </div>
                                    </td>
                                    <td class="text-end tabular-nums">{{ answer.confidence ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="card-body text-secondary" data-test="detail-no-answers">
                        {{
                            review.response
                                ? 'The model returned no per-question answers.'
                                : 'No response was received.'
                        }}
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column gap-3">
            <details class="raw" data-test="raw-request">
                <summary>Request payload</summary>
                <div class="d-flex flex-column gap-3 mt-2">
                    <CodeBlock v-for="block in requestParts" :key="block.key" :title="block.title" :text="block.text" />
                </div>
            </details>
            <details v-if="review.response" class="raw" data-test="raw-response">
                <summary>Raw response</summary>
                <div class="mt-2"><CodeBlock title="Raw response" :text="prettyJson(review.response)" /></div>
            </details>
        </div>
    </AppLayout>
</template>

<style scoped>
.raw > summary {
    cursor: pointer;
    font-weight: 500;
    padding: 0.5rem 0;
}
</style>

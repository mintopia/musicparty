<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CodeBlock from '@/components/CodeBlock.vue';
import PageHead from '@/components/PageHead.vue';
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
    () => `${formatTokens(props.review.promptTokens)} in, ${formatTokens(props.review.completionTokens)} out`,
);
const maxScore = 4;
const thresholdPercent = computed(() => Math.round(props.review.threshold * 100));
const hasScore = computed(() => props.review.score !== null);
const bands = computed(() => ({
    reject: (props.review.rejectBelow / maxScore) * 100,
    flag: ((props.review.flagBelow - props.review.rejectBelow) / maxScore) * 100,
    allow: ((maxScore - props.review.flagBelow) / maxScore) * 100,
}));
const scorePosition = computed(() => `${Math.min(100, Math.max(0, ((props.review.score ?? 0) / maxScore) * 100))}%`);
const confidencePercent = computed(() => Math.round((props.review.confidence ?? 0) * 100));
const lowConfidence = computed(
    () => props.review.confidence === null || props.review.confidence < props.review.threshold,
);
const scoredBand = computed<'reject' | 'flag' | 'allow'>(() => {
    const score = props.review.score ?? maxScore;
    return score < props.review.rejectBelow ? 'reject' : score < props.review.flagBelow ? 'flag' : 'allow';
});
const scoreNumber = computed(() => (props.review.score === null ? '' : String(Number(props.review.score.toFixed(2)))));
const scoreExplanation = computed(() => {
    const score = scoreNumber.value;
    const bandRange = {
        reject: `Reject band (below ${props.review.rejectBelow})`,
        flag: `Flag band (${props.review.rejectBelow} to ${props.review.flagBelow})`,
        allow: `Allow band (${props.review.flagBelow} and above)`,
    }[scoredBand.value];
    const verb = {reject: 'reject', flag: 'flag', allow: 'allow'}[scoredBand.value];
    if (props.review.confidence === null) {
        return `The score ${score} would ${verb} it, but the model gave no confidence, so the Request is allowed whatever the score.`;
    }
    if (lowConfidence.value) {
        return scoredBand.value === 'allow'
            ? `The score ${score} is in the ${bandRange}, and the model is only ${confidencePercent.value}% confident, below the party's ${thresholdPercent.value}% threshold, so the Request is allowed.`
            : `The score ${score} would ${verb} it, but the model is only ${confidencePercent.value}% confident, below the party's ${thresholdPercent.value}% threshold, so the Request is allowed whatever the score (band: low confidence).`;
    }
    const result = {reject: 'rejected', flag: 'flagged', allow: 'allowed'}[scoredBand.value];
    return `The score ${score} is in the ${bandRange}, and the model is ${confidencePercent.value}% confident, above the party's ${thresholdPercent.value}% threshold, so the Request is ${result}.`;
});
const fate = computed(() => {
    const when = props.review.createdAt?.human ?? '';
    const who = props.review.user ?? 'The requester';
    switch (props.review.fate.state) {
        case 'held':
            return {
                icon: 'ti-flag',
                title: 'Held in Flagged Requests',
                body: `Waiting for approval since ${props.review.fate.flaggedAt?.human ?? when}. It is not in the Queue.`,
            };
        case 'queued':
            return {
                icon: 'ti-circle-check',
                title: 'Added to the Queue',
                body:
                    props.review.outcome === 'flag'
                        ? 'It was flagged, then approved, and is in the Queue.'
                        : `Reviewed ${when} and is in the Queue.`,
            };
        case 'played':
            return {
                icon: 'ti-player-play',
                title: 'Played',
                body: `Played ${props.review.fate.playedAt?.human ?? ''}.`.replace(' .', '.'),
            };
        case 'rejected':
            return {
                icon: 'ti-ban',
                title: 'Rejected',
                body: `Not added to the Queue. ${who} was told it was rejected ${when}.`,
            };
        default:
            return {
                icon: 'ti-circle-off',
                title: 'No longer in the Queue',
                body: 'The Request was removed, rejected by a moderator or has been played.',
            };
    }
});
const busy = ref(false);

function decide(url: string | null): void {
    if (url === null || busy.value) {
        return;
    }
    router.post(
        url,
        {},
        {preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false)},
    );
}
</script>

<template>
    <AppLayout :title="`${review.track.title} - AI review - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'AI reviews', href: listUrl}, {label: 'Review'}]" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`${review.track.title} - AI review - ${party.name}`" />
        <div class="mb-2">
            <Link :href="listUrl" class="text-secondary back-link" data-test="back-link">
                <i class="icon ti ti-arrow-left me-1" aria-hidden="true"></i>All AI reviews
            </Link>
        </div>

        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="ai-reviews" />

        <PageHead :title="review.track.title">
            <template v-if="review.fate.approveUrl || review.fate.requestUrl" #default>
                <template v-if="review.fate.approveUrl">
                    <button
                        type="button"
                        class="btn btn-success touch"
                        :disabled="busy"
                        data-test="approve"
                        @click="decide(review.fate.approveUrl)"
                    >
                        <i class="icon ti ti-check me-1" aria-hidden="true"></i>Approve
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger touch"
                        :disabled="busy"
                        data-test="reject"
                        @click="decide(review.fate.rejectUrl)"
                    >
                        <i class="icon ti ti-x me-1" aria-hidden="true"></i>Reject
                    </button>
                </template>
                <Link
                    v-else-if="review.fate.requestUrl"
                    :href="review.fate.requestUrl"
                    class="btn btn-outline-secondary touch"
                    data-test="open-request-button"
                >
                    <i class="icon ti ti-external-link me-1" aria-hidden="true"></i>Open Request
                </Link>
            </template>
        </PageHead>
        <p class="text-secondary meta-line text-break" data-test="review-meta">
            <span data-test="review-artists">{{ review.track.artists }}</span>
            <span data-test="review-user"> &middot; requested by {{ review.user ?? 'Unknown' }}</span>
            <span v-if="review.createdAt" :title="review.createdAt.iso" data-test="review-when">
                , {{ review.createdAt.human }}</span
            >
        </p>
        <span class="visually-hidden" data-test="review-title">{{ review.track.title }}</span>

        <div class="row g-4 mb-4">
            <div class="col-lg-8 d-flex flex-column gap-4">
                <div class="card" data-test="verdict">
                    <div class="card-body">
                        <span class="badge fs-4 py-2 px-3 mb-3" :class="badge.class" data-test="review-outcome">
                            <i class="icon ti me-2" :class="badge.icon" aria-hidden="true"></i>{{ review.outcomeLabel }}
                        </span>
                        <p class="m-0 fs-2 text-break" data-test="detail-reason">
                            {{ review.reason ?? 'No reason given' }}
                        </p>
                    </div>
                </div>

                <div v-if="review.errorClass" class="alert alert-warning m-0" role="alert" data-test="detail-error">
                    <div class="fw-bold text-break">{{ review.errorClass }}</div>
                    <div class="text-break">{{ review.errorMessage }}</div>
                </div>

                <div v-if="hasScore" class="card" data-test="scoring">
                    <div class="card-header"><h2 class="card-title">How it scored</h2></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between fw-medium mb-2">
                            <span>Score</span>
                            <span class="tabular-nums" data-test="detail-score"
                                >{{ scoreNumber }} of {{ maxScore }}</span
                            >
                        </div>
                        <div class="band-track" role="img" :aria-label="`Score ${scoreNumber} of ${maxScore}`">
                            <span class="band is-reject" :style="{width: `${bands.reject}%`}"></span>
                            <span class="band is-flag" :style="{width: `${bands.flag}%`}"></span>
                            <span class="band is-allow" :style="{width: `${bands.allow}%`}"></span>
                            <span class="marker" :style="{left: scorePosition}"></span>
                        </div>
                        <div class="scale small text-secondary">
                            <span>Reject</span>
                            <span :style="{left: `${bands.reject}%`}">Flag</span>
                            <span :style="{left: `${bands.reject + bands.flag}%`}">Allow</span>
                            <span class="end">{{ maxScore }}</span>
                        </div>

                        <div class="d-flex justify-content-between fw-medium mt-4 mb-2">
                            <span>Confidence</span>
                            <span class="tabular-nums" data-test="detail-confidence">
                                {{ review.confidence === null ? '-' : formatPercent(review.confidence) }}
                            </span>
                        </div>
                        <div
                            class="confidence-track"
                            role="img"
                            :aria-label="`Confidence ${confidencePercent}%, threshold ${thresholdPercent}%`"
                        >
                            <span class="fill" :style="{width: `${confidencePercent}%`}"></span>
                            <span class="marker is-threshold" :style="{left: `${thresholdPercent}%`}"></span>
                        </div>
                        <div class="scale small text-secondary">
                            <span>0%</span>
                            <span class="mid" :style="{left: `${thresholdPercent}%`}"
                                >Threshold {{ thresholdPercent }}%</span
                            >
                            <span class="end">100%</span>
                        </div>
                        <p class="mt-3 mb-0 text-break" data-test="score-explanation">{{ scoreExplanation }}</p>
                    </div>
                </div>

                <div v-if="criterionBreakdown.length > 0" class="card" data-test="criterion">
                    <div class="card-header"><h2 class="card-title">Criterion</h2></div>
                    <div class="card-body" data-test="failing-criterion">
                        <div class="mb-3">
                            <strong>{{ review.criterionLabel ?? 'None' }}</strong>
                            <span v-if="review.criterionConfidence !== null" class="text-secondary tabular-nums ms-1"
                                >{{ Math.round(review.criterionConfidence * 100) }}% confident</span
                            >
                        </div>
                        <div data-test="criterion-breakdown">
                            <div
                                v-for="p in criterionBreakdown"
                                :key="p.key"
                                class="row align-items-center g-2 mb-2"
                                data-test="criterion-probability"
                            >
                                <div class="col-4 col-md-3 text-break">{{ p.label }}</div>
                                <div class="col">
                                    <div class="progress" role="presentation">
                                        <div class="progress-bar" :style="{width: `${p.percent}%`}"></div>
                                    </div>
                                </div>
                                <div class="col-auto tabular-nums text-secondary percent-col">{{ p.percent }}%</div>
                            </div>
                        </div>
                        <p class="small text-secondary mt-3 mb-0">
                            The criterion explains a flag or reject. It never changes the decision.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 d-flex flex-column gap-4">
                <div class="card" data-test="fate">
                    <div class="card-header"><h2 class="card-title">What happened to the Request</h2></div>
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <i class="icon ti text-secondary mt-1" :class="fate.icon" aria-hidden="true"></i>
                            <div>
                                <div class="fw-medium" data-test="fate-title">{{ fate.title }}</div>
                                <div class="text-secondary" data-test="fate-body">{{ fate.body }}</div>
                            </div>
                        </div>
                        <Link
                            v-if="review.fate.requestUrl && review.fate.approveUrl"
                            :href="review.fate.requestUrl"
                            class="d-inline-flex align-items-center mt-3 touch-link"
                            data-test="open-request"
                            >Open Request</Link
                        >
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Review details</h2></div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-4 text-secondary fw-normal">Model</dt>
                            <dd class="col-8 text-break" data-test="detail-model">
                                {{ review.model ?? '-' }} ({{ review.driver }})
                            </dd>
                            <dt class="col-4 text-secondary fw-normal">Latency</dt>
                            <dd class="col-8 tabular-nums" data-test="detail-latency">
                                {{ formatLatency(review.latencyMs) }}
                            </dd>
                            <dt class="col-4 text-secondary fw-normal">Tokens</dt>
                            <dd class="col-8 tabular-nums" data-test="detail-tokens">{{ tokens }}</dd>
                            <dt class="col-4 text-secondary fw-normal">Requested by</dt>
                            <dd class="col-8 mb-0">{{ review.user ?? 'Unknown' }}</dd>
                        </dl>
                        <details v-if="answers.length > 0" class="raw" data-test="answers">
                            <summary>Model answers</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-vcenter">
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
                        </details>
                        <p v-else class="text-secondary small mb-2" data-test="detail-no-answers">
                            {{
                                review.response
                                    ? 'The model returned no per-question answers.'
                                    : 'No response was received.'
                            }}
                        </p>
                        <details class="raw" data-test="raw-request">
                            <summary>Request payload</summary>
                            <div class="d-flex flex-column gap-3 mt-2">
                                <CodeBlock
                                    v-for="block in requestParts"
                                    :key="block.key"
                                    :title="block.title"
                                    :text="block.text"
                                />
                            </div>
                        </details>
                        <details v-if="review.response" class="raw" data-test="raw-response">
                            <summary>Raw response</summary>
                            <div class="mt-2">
                                <CodeBlock title="Raw response" :text="prettyJson(review.response)" />
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.meta-line {
    margin-top: -0.75rem;
    margin-bottom: 1.5rem;
}

:deep(.mp-page-head) > div:first-child {
    margin-inline-end: auto;
}

@media (max-width: 767.98px) {
    .meta-line {
        margin-top: 0;
    }
}

.back-link,
.touch-link {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
}

@media (max-width: 767.98px) {
    .touch {
        min-height: 44px;
        width: 100%;
    }
}

.raw > summary {
    cursor: pointer;
    min-height: 44px;
    display: flex;
    align-items: center;
    list-style: none;
}

.raw > summary::before {
    content: '';
    width: 0.45rem;
    height: 0.45rem;
    margin-inline-end: 0.75rem;
    border: solid currentcolor;
    border-width: 0 2px 2px 0;
    transform: rotate(-45deg);
    transition: transform 0.15s;
}

.raw[open] > summary::before {
    transform: rotate(45deg);
}

.percent-col {
    min-width: 3.5rem;
}

.band-track,
.confidence-track {
    position: relative;
    display: flex;
    height: 0.75rem;
    border-radius: 0.375rem;
    background: var(--tblr-border-color);
}

.band-track {
    overflow: visible;
}

.band:first-child {
    border-radius: 0.375rem 0 0 0.375rem;
}

.band:nth-child(3) {
    border-radius: 0 0.375rem 0.375rem 0;
}

.band.is-reject {
    background: color-mix(in srgb, var(--tblr-red) 45%, var(--tblr-bg-surface));
}

.band.is-flag {
    background: color-mix(in srgb, var(--tblr-yellow) 55%, var(--tblr-bg-surface));
}

.band.is-allow {
    background: color-mix(in srgb, var(--tblr-green) 45%, var(--tblr-bg-surface));
}

.confidence-track .fill {
    background: var(--tblr-primary);
    border-radius: 0.375rem 0 0 0.375rem;
}

.marker {
    position: absolute;
    top: -0.25rem;
    bottom: -0.25rem;
    width: 4px;
    margin-left: -2px;
    border-radius: 2px;
    background: var(--tblr-body-color);
}

.marker.is-threshold {
    width: 2px;
    margin-left: -1px;
    opacity: 0.7;
}

.scale {
    position: relative;
    display: flex;
    justify-content: space-between;
    margin-top: 0.5rem;
    height: 1.25rem;
}

.scale > span[style] {
    position: absolute;
    transform: translateX(0);
}

.scale > .mid {
    transform: translateX(-50%);
}

.scale > .end {
    margin-left: auto;
}
</style>

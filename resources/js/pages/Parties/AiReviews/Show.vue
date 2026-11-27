<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AiReviewDetailsCard from '@/components/AiReviews/AiReviewDetailsCard.vue';
import AiReviewFateCard from '@/components/AiReviews/AiReviewFateCard.vue';
import AiReviewScoreCard from '@/components/AiReviews/AiReviewScoreCard.vue';
import PageHead from '@/components/PageHead.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {aiReviewsUrl, answerRows, outcomeBadge} from '@/lib/aiReviews';
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
const criterionBreakdown = computed(
    () =>
        answerRows(props.review.response, props.criterionLabels).find((answer) => answer.id === 'criterion')
            ?.probabilities ?? [],
);
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

                <AiReviewScoreCard v-if="review.score !== null" :review="review" />

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
                <AiReviewFateCard :review="review" />

                <AiReviewDetailsCard :review="review" :criterion-labels="criterionLabels" />
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

.back-link {
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

.percent-col {
    min-width: 3.5rem;
}
</style>

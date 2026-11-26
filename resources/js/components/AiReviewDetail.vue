<script setup lang="ts">
import {computed} from 'vue';
import CodeBlock from '@/components/CodeBlock.vue';
import {formatLatency, formatTokens, prettyJson, requestBlocks} from '@/lib/aiReviews';
import type {AiReviewDetail} from '@/types';

const props = defineProps<{review: AiReviewDetail}>();

const request = computed(() => requestBlocks(props.review.request));
const tokens = computed(
    () =>
        `${formatTokens(props.review.promptTokens)} in, ${formatTokens(props.review.completionTokens)} out, ${formatTokens(props.review.totalTokens)} total`,
);
</script>

<template>
    <div class="row g-4 py-2" data-test="ai-review-detail">
        <div class="col-lg-4 col-xxl-3">
            <p class="mb-3" data-test="detail-reason">{{ review.reason ?? 'No reason given' }}</p>
            <dl class="row mb-0 small">
                <dt class="col-4 text-secondary fw-normal">Model</dt>
                <dd class="col-8 text-break">{{ review.model ?? '-' }} ({{ review.driver }})</dd>
                <dt class="col-4 text-secondary fw-normal">Endpoint</dt>
                <dd class="col-8 text-break">{{ review.endpointHost ?? '-' }}</dd>
                <dt class="col-4 text-secondary fw-normal">Score</dt>
                <dd class="col-8 tabular-nums">{{ review.score ?? '-' }}</dd>
                <dt class="col-4 text-secondary fw-normal">Confidence</dt>
                <dd class="col-8 tabular-nums">{{ review.confidence ?? '-' }}</dd>
                <dt class="col-4 text-secondary fw-normal">Latency</dt>
                <dd class="col-8 tabular-nums" data-test="detail-latency">{{ formatLatency(review.latencyMs) }}</dd>
                <dt class="col-4 text-secondary fw-normal">Tokens</dt>
                <dd class="col-8 tabular-nums">{{ tokens }}</dd>
                <dt class="col-4 text-secondary fw-normal">Queued</dt>
                <dd class="col-8">{{ review.requested ? 'Yes' : 'No' }}</dd>
            </dl>
            <div v-if="review.errorClass" class="alert alert-warning mt-3 mb-0" role="alert" data-test="detail-error">
                <div class="fw-bold text-break">{{ review.errorClass }}</div>
                <div class="text-break">{{ review.errorMessage }}</div>
            </div>
        </div>
        <div class="col-lg-8 col-xxl-9 d-flex flex-column gap-3">
            <CodeBlock v-if="review.partyContext" title="Party context sent" :text="review.partyContext" />
            <CodeBlock v-for="block in request" :key="block.key" :title="block.title" :text="block.text" />
            <CodeBlock v-if="review.response" title="Raw response" :text="prettyJson(review.response)" />
            <p v-else class="text-secondary mb-0" data-test="detail-no-response">No response was received.</p>
        </div>
    </div>
</template>

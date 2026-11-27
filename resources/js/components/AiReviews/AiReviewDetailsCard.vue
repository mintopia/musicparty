<script setup lang="ts">
import {computed} from 'vue';
import CodeBlock from '@/components/CodeBlock.vue';
import {answerRows, formatLatency, formatTokens, prettyJson, requestBlocks} from '@/lib/aiReviews';
import type {AiReviewDetail} from '@/types';

const props = defineProps<{review: AiReviewDetail; criterionLabels: Record<string, string>}>();

const answers = computed(() => answerRows(props.review.response, props.criterionLabels));
const requestParts = computed(() => requestBlocks(props.review.request));
const tokens = computed(
    () => `${formatTokens(props.review.promptTokens)} in, ${formatTokens(props.review.completionTokens)} out`,
);
</script>

<template>
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
                                        <span v-for="p in answer.probabilities" :key="p.key" class="me-3 text-nowrap"
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
                {{ review.response ? 'The model returned no per-question answers.' : 'No response was received.' }}
            </p>
            <details class="raw" data-test="raw-request">
                <summary>Request payload</summary>
                <div class="d-flex flex-column gap-3 mt-2">
                    <CodeBlock v-for="block in requestParts" :key="block.key" :title="block.title" :text="block.text" />
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
</template>

<style scoped>
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
</style>

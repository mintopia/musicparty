<script setup lang="ts">
import {computed} from 'vue';
import {formatPercent} from '@/lib/aiReviews';
import type {AiReviewDetail} from '@/types';

const props = defineProps<{review: AiReviewDetail}>();

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
</script>

<template>
    <div v-if="hasScore" class="card" data-test="scoring">
        <div class="card-header"><h2 class="card-title">How it scored</h2></div>
        <div class="card-body">
            <div class="d-flex justify-content-between fw-medium mb-2">
                <span>Score</span>
                <span class="tabular-nums" data-test="detail-score">{{ scoreNumber }} of {{ maxScore }}</span>
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
                <span class="mid" :style="{left: `${thresholdPercent}%`}">Threshold {{ thresholdPercent }}%</span>
                <span class="end">100%</span>
            </div>
            <p class="mt-3 mb-0 text-break" data-test="score-explanation">{{ scoreExplanation }}</p>
        </div>
    </div>
</template>

<style scoped>
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

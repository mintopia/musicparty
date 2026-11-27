<script setup lang="ts">
import {computed} from 'vue';
import {Link} from '@inertiajs/vue3';
import type {AiReviewDetail} from '@/types';

const props = defineProps<{review: AiReviewDetail}>();

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
</script>

<template>
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
</template>

<style scoped>
.touch-link {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
}
</style>

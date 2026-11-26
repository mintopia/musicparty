<script setup lang="ts">
import {computed} from 'vue';
import {aiReviewsUrl} from '@/lib/aiReviews';
import {flaggedUrl} from '@/lib/flagged';
import {index as moderationIndex} from '@/routes/parties/moderation';
import {index as songsIndex} from '@/routes/parties/songs';
import type {ModerationTabsProps} from '@/types';

const props = defineProps<{
    code: string;
    tabs: ModerationTabsProps;
    active: 'flagged' | 'requests' | 'rules' | 'ai-reviews';
}>();

const items = computed(() => [
    {key: 'flagged', label: 'Flagged', href: flaggedUrl(props.code), count: props.tabs.flaggedCount},
    {key: 'requests', label: 'Requests', href: songsIndex(props.code).url, count: 0},
    {key: 'rules', label: 'Rules', href: moderationIndex(props.code).url, count: 0},
    ...(props.tabs.aiReviewEnabled
        ? [{key: 'ai-reviews', label: 'AI reviews', href: aiReviewsUrl(props.code), count: 0}]
        : []),
]);
</script>

<template>
    <nav class="underline-tabs mb-4" aria-label="Moderation" data-test="moderation-tabs">
        <a
            v-for="item in items"
            :key="item.key"
            class="underline-tab"
            :class="{active: item.key === active}"
            :aria-current="item.key === active ? 'page' : undefined"
            :href="item.href"
            :data-test="`tab-${item.key}`"
            >{{ item.label
            }}<span v-if="item.count > 0" class="badge bg-red text-red-fg ms-2">{{ item.count }}</span></a
        >
    </nav>
</template>

<script setup>
import TrackThumb from './TrackThumb.vue';
import {formatDuration} from '../lib/format';

defineProps({queue: {type: Array, default: () => []}});
</script>

<template>
    <p v-if="queue.length === 0" data-testid="queue-empty" class="text-sm text-muted">The queue is empty. Search for a track to request one.</p>
    <ul v-else data-testid="queue-list" class="divide-y divide-border overflow-hidden rounded border border-border">
        <li v-for="item in queue" :key="item.id" data-testid="queue-item" class="flex items-center gap-3 px-3 py-3 md:px-5">
            <TrackThumb :src="item.track.artwork_url" />
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium">{{ item.track.title }}</div>
                <div class="truncate text-sm text-muted">{{ item.track.artists.join(', ') }}</div>
                <div class="truncate text-xs text-muted">Requested by {{ item.requested_by.name }}</div>
            </div>
            <span class="hidden text-sm text-muted sm:inline">{{ formatDuration(item.track.duration_ms) }}</span>
            <span
                data-testid="queue-score"
                class="min-w-8 text-center text-base font-semibold"
                :class="{'text-accent': item.score > 0, 'text-danger': item.score < 0}"
            >{{ item.score }}</span>
        </li>
    </ul>
</template>

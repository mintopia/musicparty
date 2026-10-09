<script setup>
import Icon from './Icon.vue';

defineProps({nowPlaying: {type: Object, default: null}});
</script>

<template>
    <div
        data-testid="mini-now-playing"
        class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex items-center gap-3 border-t border-border bg-surface px-4 py-2 md:hidden"
    >
        <div class="min-w-0 flex-1">
            <template v-if="nowPlaying">
                <div class="truncate text-sm font-medium">{{ nowPlaying.title }}</div>
                <div class="truncate text-xs text-muted">{{ nowPlaying.artist }}</div>
            </template>
            <div v-else class="text-sm text-muted">Nothing playing</div>
        </div>
        <button
            type="button"
            disabled
            aria-label="Expand now playing"
            class="flex h-11 w-11 items-center justify-center rounded text-muted"
        >
            <Icon name="chevron" class="rotate-180" />
        </button>
    </div>
</template>

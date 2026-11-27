<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';

const focusSearch = (): void => document.getElementById('query')?.focus();
</script>

<template>
    <EmptyState
        icon="ti-playlist-off"
        title="The Queue is empty"
        description="The party is waiting on you. Search for a track to add the first one."
    >
        <button type="button" class="btn btn-primary" data-test="empty-search" @click="focusSearch">
            <i class="icon ti ti-music-search me-1"></i>Search for a track
        </button>
    </EmptyState>
</template>

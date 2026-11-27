<script setup lang="ts">
defineProps<{title: string; description?: string; icon?: string; allClear?: boolean}>();
</script>

<template>
    <div class="empty text-center py-5" :class="{'mp-all-clear': allClear}" data-test="empty-state">
        <div v-if="icon" class="empty-icon"><i class="icon ti" :class="icon"></i></div>
        <p class="empty-title">{{ title }}</p>
        <p v-if="description" class="empty-subtitle text-secondary">{{ description }}</p>
        <div class="empty-action"><slot /></div>
    </div>
</template>

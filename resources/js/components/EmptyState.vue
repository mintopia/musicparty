<script setup lang="ts">
defineProps<{title: string; description?: string}>();
</script>

<template>
    <div class="empty text-center py-5" data-test="empty-state">
        <p class="empty-title">{{ title }}</p>
        <p v-if="description" class="empty-subtitle text-secondary">{{ description }}</p>
        <div class="empty-action"><slot /></div>
    </div>
</template>

<script setup lang="ts">
import SearchCover from '@/components/Search/SearchCover.vue';

withDefaults(
    defineProps<{
        href: string;
        title: string;
        subtitle: string;
        image: string | null;
        round?: boolean;
        icon?: string;
    }>(),
    {round: false, icon: 'ti-disc'},
);
</script>

<template>
    <a
        :href="href"
        class="list-group-item list-group-item-action d-flex align-items-center gap-3"
        data-test="entity-row"
    >
        <SearchCover :src="image" :alt="title" :size="48" :round="round" :icon="icon" />
        <div class="entity-main">
            <div class="fw-medium" data-test="entity-title">{{ title }}</div>
            <div class="text-secondary small" data-test="entity-subtitle">{{ subtitle }}</div>
        </div>
        <i class="icon ti ti-chevron-right ms-auto text-secondary"></i>
    </a>
</template>

<style scoped>
.entity-main {
    min-width: 0;
    overflow-wrap: anywhere;
}
</style>

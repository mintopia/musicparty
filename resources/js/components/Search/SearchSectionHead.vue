<script setup lang="ts">
defineProps<{title: string; count?: string; seeAllHref?: string; seeAllLabel?: string}>();
</script>

<template>
    <div class="d-flex align-items-baseline gap-3 mt-4 mb-2" data-test="section-head">
        <h2 class="section-title m-0">{{ title }}</h2>
        <span v-if="count" class="text-secondary small" data-test="section-count">{{ count }}</span>
        <a v-if="seeAllHref" :href="seeAllHref" class="ms-auto small text-nowrap" data-test="see-all">
            {{ seeAllLabel }}
            <i class="icon ti ti-chevron-right"></i>
        </a>
    </div>
</template>

<style scoped>
.section-title {
    font-size: 1.05rem;
}
</style>

<script setup lang="ts">
defineProps<{title: string; summary?: string}>();
</script>

<template>
    <div class="mp-page-head page-header mt-0">
        <div v-if="$slots.lead" class="mp-cell">
            <slot name="lead" />
            <div>
                <h1>{{ title }}</h1>
                <p v-if="summary">{{ summary }}</p>
            </div>
        </div>
        <div v-else>
            <h1>{{ title }}</h1>
            <p v-if="summary">{{ summary }}</p>
        </div>
        <div v-if="$slots.default" class="btn-list"><slot /></div>
    </div>
</template>

<script setup>
defineProps({items: {type: Array, required: true}});
</script>

<template>
    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in items" :key="item.label" :data-grid-item="item.label">
            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ item.label }}</dt>
            <dd class="mt-1 text-sm"><slot name="value" :item="item">{{ item.value }}</slot></dd>
        </div>
    </dl>
</template>

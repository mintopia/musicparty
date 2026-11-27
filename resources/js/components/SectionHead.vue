<script setup lang="ts">
defineProps<{title: string; count?: number; attention?: boolean}>();
</script>

<template>
    <div class="mp-section-head" data-test="section-head">
        <h2>{{ title }}</h2>
        <span v-if="count !== undefined" class="mp-count" :class="{'is-attention': attention}" data-test="section-count">{{ count }}</span>
        <div v-if="$slots.default" class="mp-section-actions"><slot /></div>
    </div>
</template>

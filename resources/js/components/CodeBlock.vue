<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';

defineProps<{title: string; text: string}>();
</script>

<template>
    <section class="code-block" data-test="code-block">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <h3 class="m-0 fs-5">{{ title }}</h3>
            <CopyButton :value="text" :label="`Copy ${title.toLowerCase()}`" />
        </div>
        <pre class="code-block-text border rounded p-3 mb-0"><code>{{ text }}</code></pre>
    </section>
</template>

<style scoped>
.code-block-text {
    max-height: 22rem;
    overflow: auto;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-size: 0.8125rem;
    color: var(--tblr-body-color);
    background: var(--tblr-bg-surface-secondary);
    border-color: var(--tblr-border-color);
}
</style>

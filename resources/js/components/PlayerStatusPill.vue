<script setup lang="ts">
defineProps<{warn?: boolean}>();
</script>

<template>
    <span class="status-pill" :class="{warn}" data-test="status-pill">
        <span v-if="warn" class="status-dot" aria-hidden="true"></span>
        <slot />
    </span>
</template>

<style scoped>
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5em;
    padding: 0.25em 0.75em;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-size: 12px;
    font-weight: 550;
    line-height: 1.3;
    white-space: nowrap;
}

.status-pill.warn {
    background: rgba(255, 196, 0, 0.18);
    color: #ffd866;
}

.status-dot {
    inline-size: 0.6em;
    block-size: 0.6em;
    border-radius: 50%;
    background: currentColor;
}
</style>

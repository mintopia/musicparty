<script setup>
defineProps({variant: {type: String, default: 'muted'}});

const classes = {
    green: 'bg-accent/15 text-accent',
    red: 'bg-danger/15 text-danger',
    muted: 'bg-muted/15 text-muted',
};
</script>

<template>
    <span class="inline-block rounded-full px-3 py-0.5 text-sm font-medium" :class="classes[variant] ?? classes.muted">
        <slot />
    </span>
</template>

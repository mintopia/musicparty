<script setup>
import FlashAlerts from '../Components/ui/FlashAlerts.vue';
import AppShell from './AppShell.vue';
</script>

<template>
    <AppShell v-slot="{meta}">
        <div class="px-6 py-6">
            <h1 v-if="meta.declared" class="mb-5 text-2xl font-semibold">{{ meta.title }}</h1>
            <FlashAlerts />
            <slot />
        </div>
    </AppShell>
</template>

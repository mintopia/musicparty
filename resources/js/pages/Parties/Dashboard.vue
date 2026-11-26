<script setup lang="ts">
import type {DashboardProps} from '@/types';

defineProps<DashboardProps>();
</script>

<template>
    <div data-test="dashboard">{{ party.name }}</div>
</template>

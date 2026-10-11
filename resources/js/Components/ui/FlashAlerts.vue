<script setup>
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Alert from './Alert.vue';

const page = usePage();
const variants = [['success', 'success'], ['error', 'danger'], ['warning', 'warning'], ['info', 'info']];
const alerts = computed(() => variants
    .map(([key, variant]) => ({key, variant, message: page.props.flash?.[key]}))
    .filter((alert) => alert.message));
</script>

<template>
    <div v-if="alerts.length" class="mb-4 space-y-2" data-testid="flash-alerts">
        <Alert v-for="alert in alerts" :key="alert.key" :variant="alert.variant">{{ alert.message }}</Alert>
    </div>
</template>

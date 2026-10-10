<script setup>
import {Head} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

const props = defineProps({links: {type: Object, required: true}});

const cards = computed(() => [
    {key: 'horizon', label: 'Horizon', description: 'Queue workers and jobs'},
    {key: 'pulse', label: 'Pulse', description: 'Application performance'},
    {key: 'telescope', label: 'Telescope', description: 'Requests, exceptions and logs'},
].map((card) => ({...card, href: props.links[card.key] ?? null})));
</script>

<template>
    <Head title="Admin" />
    <h1 class="text-xl font-semibold">Admin dashboard</h1>
    <ul class="mt-4 grid gap-4 sm:grid-cols-3">
        <li
            v-for="card in cards"
            :key="card.key"
            :data-testid="`card-${card.key}`"
            class="rounded border border-border bg-surface p-4"
        >
            <h2 class="font-medium">{{ card.label }}</h2>
            <p class="mt-1 text-sm text-muted">{{ card.description }}</p>
            <a v-if="card.href" :href="card.href" class="mt-3 inline-block text-sm text-primary underline">
                Open {{ card.label }}
            </a>
            <span v-else class="mt-3 inline-block text-sm text-muted" aria-disabled="true">Unavailable</span>
        </li>
    </ul>
</template>

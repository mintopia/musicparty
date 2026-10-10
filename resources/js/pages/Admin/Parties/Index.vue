<script setup>
import {Head, router} from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

defineProps({parties: {type: Array, required: true}});

const enter = (party) => router.post(`/admin/parties/${party.id}/act-as-host`, {}, {preserveScroll: true});
const leave = (party) => router.delete(`/admin/parties/${party.id}/act-as-host`, {preserveScroll: true});
</script>

<template>
    <Head title="Parties" />
    <h1 class="text-xl font-semibold">Parties</h1>
    <ul class="mt-4 divide-y divide-border">
        <li
            v-for="party in parties"
            :key="party.id"
            :data-testid="`party-${party.id}`"
            class="flex items-center gap-3 py-3"
        >
            <span class="flex-1">{{ party.name }}</span>
            <span
                v-if="party.acting_as_host"
                data-testid="acting-badge"
                class="rounded bg-primary px-2 py-0.5 text-xs font-semibold"
            >Acting as Host</span>
            <button
                v-if="party.acting_as_host"
                type="button"
                class="rounded border border-border px-2 py-1 text-sm"
                :aria-label="`Leave Act-as-Host for ${party.name}`"
                @click="leave(party)"
            >Leave Act-as-Host</button>
            <button
                v-else
                type="button"
                class="rounded border border-border px-2 py-1 text-sm"
                :aria-label="`Enter Act-as-Host for ${party.name}`"
                @click="enter(party)"
            >Enter Act-as-Host</button>
        </li>
        <li v-if="!parties.length" class="py-3 text-muted">No parties.</li>
    </ul>
</template>

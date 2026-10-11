<script setup>
import {Head, router} from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Badge from '../../../Components/ui/Badge.vue';
import Button from '../../../Components/ui/Button.vue';
import Card from '../../../Components/ui/Card.vue';
import DataTable from '../../../Components/ui/DataTable.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

defineProps({parties: {type: Array, required: true}});

usePageMeta({title: 'Parties', crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Parties'}]});

const columns = [
    {key: 'name', label: 'Name'},
    {key: 'status', label: 'Status'},
    {key: 'actions', label: '', class: 'text-right'},
];

const enter = (party) => router.post(`/admin/parties/${party.id}/act-as-host`, {}, {preserveScroll: true});
const leave = (party) => router.delete(`/admin/parties/${party.id}/act-as-host`, {preserveScroll: true});
</script>

<template>
    <Head title="Parties" />
    <Card flush>
        <DataTable
            :columns="columns"
            :rows="parties"
            empty-text="No parties."
            :row-attrs="(party) => ({'data-testid': `party-${party.id}`})"
        >
            <template #row="{row: party}">
                <td class="px-5 py-3">{{ party.name }}</td>
                <td class="px-5 py-3">
                    <Badge v-if="party.acting_as_host" data-testid="acting-badge">Acting as Host</Badge>
                </td>
                <td class="px-5 py-3 text-right">
                    <Button
                        v-if="party.acting_as_host"
                        variant="outline-danger"
                        :aria-label="`Leave Act-as-Host for ${party.name}`"
                        @click="leave(party)"
                    >Leave Act-as-Host</Button>
                    <Button
                        v-else
                        variant="outline-primary"
                        :aria-label="`Enter Act-as-Host for ${party.name}`"
                        @click="enter(party)"
                    >Enter Act-as-Host</Button>
                </td>
            </template>
        </DataTable>
    </Card>
</template>

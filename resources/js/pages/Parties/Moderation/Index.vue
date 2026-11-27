<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {moderationCrumbs} from '@/lib/breadcrumbs';
import {Head, Link} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ModerationTabs from '@/components/ModerationTabs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState, ModerationTabsProps, Paginator} from '@/types';

interface ModerationRow {
    id: number;
    enabled: boolean;
    regex: boolean;
    type_name: string | null;
    value: string;
    notes: string | null;
}

defineProps<{
    party: {code: string; name: string; canManage: boolean; ownerName?: string | null};
    playerState: LegacyPartyState;
    moderations: Paginator<ModerationRow>;
    moderationTabs: ModerationTabsProps;
}>();
</script>

<template>
    <AppLayout :title="`Moderation - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="moderationCrumbs(party)" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Moderation - ${party.name}`" />
        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="rules" />
        <div v-if="!party.canManage" class="text-secondary mb-3" data-test="rules-readonly">
            <i class="ti ti-lock me-1" aria-hidden="true"></i>Rules are read-only for moderators. Only
            {{ party.ownerName ?? 'the owner' }} can add, edit or delete them.
        </div>
        <div v-if="party.canManage && moderations.data.length > 0" class="d-flex justify-content-end mb-3 d-print-none">
            <Link :href="`/parties/${party.code}/moderation/create`" class="btn btn-primary">
                <i class="icon ti ti-plus"></i>
                Add rule
            </Link>
        </div>

        <EmptyState
            v-if="moderations.data.length === 0"
            icon="ti-filter-off"
            title="No moderation rules"
            description="Rules block or flag tracks by artist, album, title or pattern before they reach the queue."
        >
            <Link v-if="party.canManage" :href="`/parties/${party.code}/moderation/create`" class="btn btn-primary">
                <i class="icon ti ti-plus"></i>
                Add rule
            </Link>
        </EmptyState>
        <div v-else class="card">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter text-nowrap card-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Enabled</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Regex</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="moderation in moderations.data" :key="moderation.id">
                            <td>
                                <Link
                                    v-if="party.canManage"
                                    :href="`/parties/${party.code}/moderation/${moderation.id}/edit`"
                                    >{{ moderation.id }}</Link
                                >
                                <template v-else>{{ moderation.id }}</template>
                            </td>
                            <td>
                                <span v-if="moderation.enabled" class="status status-green">Yes</span>
                                <span v-else class="status status-red">No</span>
                            </td>
                            <td>{{ moderation.type_name }}</td>
                            <td>
                                <code v-if="moderation.regex">{{ moderation.value }}</code>
                                <template v-else>{{ moderation.value }}</template>
                            </td>
                            <td>{{ moderation.regex ? 'Yes' : 'No' }}</td>
                            <td>{{ moderation.notes }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="moderations.last_page > 1" class="card-footer d-flex">
                <Pagination class="ms-auto" :paginator="moderations" />
            </div>
        </div>
    </AppLayout>
</template>

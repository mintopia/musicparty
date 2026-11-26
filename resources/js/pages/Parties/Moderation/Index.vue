<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import ModerationBreadcrumbs from '@/components/ModerationBreadcrumbs.vue';
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
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    moderations: Paginator<ModerationRow>;
    moderationTabs: ModerationTabsProps;
}>();
</script>

<template>
    <AppLayout :title="`Rules - ${party.name}`">
        <template #breadcrumbs>
            <ModerationBreadcrumbs :party="party" active />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Rules - ${party.name}`" />
        <ModerationTabs :code="party.code" :tabs="moderationTabs" active="rules" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="row">
                <div v-if="moderations.data.length > 0" class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <Link :href="`/parties/${party.code}/moderation/create`" class="btn btn-primary d-inline-block">
                            <i class="icon ti ti-plus"></i>
                            Create Filter
                        </Link>
                    </div>
                </div>
            </div>

            <EmptyState
                v-if="moderations.data.length === 0"
                icon="ti-filter-off"
                title="No moderation rules"
                description="Rules block or flag tracks by artist, album, title or pattern before they reach the queue."
            >
                <Link :href="`/parties/${party.code}/moderation/create`" class="btn btn-primary">
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
                                    <Link :href="`/parties/${party.code}/moderation/${moderation.id}/edit`">{{
                                        moderation.id
                                    }}</Link>
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
        </div>
    </AppLayout>
</template>

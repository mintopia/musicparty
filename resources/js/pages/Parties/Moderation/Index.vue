<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import ModerationBreadcrumbs from '@/components/ModerationBreadcrumbs.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {Paginator} from '@/types';

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
    moderations: Paginator<ModerationRow>;
}>();
</script>

<template>
    <AppLayout :title="`Moderation - ${party.name}`">
        <template #breadcrumbs>
            <ModerationBreadcrumbs :party="party" active />
        </template>
        <Head :title="`Moderation - ${party.name}`" />
        <div class="row">
            <div class="col page-header mt-2">
                <h1>Moderation</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <Link :href="`/parties/${party.code}/moderation/create`" class="btn btn-primary d-inline-block">
                        <i class="icon ti ti-plus"></i>
                        Create Filter
                    </Link>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
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
        </div>
    </AppLayout>
</template>

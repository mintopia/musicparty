<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{stats: {users: {total: number; lastWeek: number}}}>();
</script>

<template>
    <AppLayout title="Dashboard">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Admin'}]" />
        </template>
        <Head title="Dashboard" />
        <div class="page-header mt-0">
            <h1>Dashboard</h1>
        </div>
        <div class="row row-deck row-cards">
            <div class="col-sm-6 col-xl-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar">
                                    <i class="icon ti ti-user"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="fw-medium">
                                    <Link href="/admin/users" class="text-body text-decoration-none stretched-link"
                                        >{{ stats.users.total }} Users</Link
                                    >
                                </div>
                                <div class="text-secondary">{{ stats.users.lastWeek }} in the last week</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';

defineProps<{user: {nickname: string; email: string; avatar: string}}>();
</script>

<template>
    <AppLayout title="Home">
        <template #breadcrumbs>
            <li class="breadcrumb-item active">
                <a :href="home().url">Home</a>
            </li>
        </template>
        <Head title="Home" />
        <div class="row">
            <div class="col-xl-9 col-sm-8 mb-4"></div>
            <div class="col-xl-3 col-sm-4 mt-sm-2">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <span
                                class="avatar avatar-xl rounded"
                                :style="{backgroundImage: `url('${user.avatar}')`}"
                            ></span>
                        </div>
                        <div class="card-title mb-1">{{ user.nickname }}</div>
                        <div class="text-secondary">
                            <span class="text-muted">{{ user.email }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

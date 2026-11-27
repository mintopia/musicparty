<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {Link, usePage} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type {SharedProps} from '@/types';

const page = usePage<SharedProps>();
</script>

<template>
    <AppLayout title="Shell">
        <template #breadcrumbs>
            <Breadcrumbs :items="[{label: 'Shell'}]" />
        </template>
        <div class="card">
            <div class="card-body">
                <h2 class="card-title">{{ page.props.instanceName }}</h2>
                <p v-if="page.props.auth.user">Signed in as {{ page.props.auth.user.nickname }}</p>
                <p v-else>Guest</p>
                <Link href="/ui/shell">Reload</Link>
            </div>
        </div>
    </AppLayout>
</template>

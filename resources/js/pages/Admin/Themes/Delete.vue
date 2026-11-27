<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{theme: {id: number; name: string}}>();

const form = useForm({});

function submit(): void {
    form.delete(`/admin/settings/themes/${props.theme.id}`);
}
</script>

<template>
    <AppLayout :title="`Delete ${theme.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminSettingsCrumbs(), {label: 'Delete Theme'}]" />
        </template>
        <Head :title="`Delete ${theme.name}`" />
        <div class="page-header mt-0">
            <h1>Delete {{ theme.name }}</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                    <p class="mt-4">
                        Are you sure you want to delete <strong>{{ theme.name }}</strong
                        >?
                    </p>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link href="/admin/settings" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

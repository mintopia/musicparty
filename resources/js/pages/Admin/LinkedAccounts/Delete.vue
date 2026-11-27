<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    user: {id: number; nickname: string};
    account: {id: number; provider: string};
}>();

const form = useForm({});

function submit(): void {
    form.delete(`/admin/users/${props.user.id}/accounts/${props.account.id}`);
}
</script>

<template>
    <AppLayout :title="`Remove Linked ${account.provider} Account`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminUserCrumbs(user), {label: `Remove Linked ${account.provider} Account`}]" />
        </template>
        <Head :title="`Remove Linked ${account.provider} Account`" />
        <div class="page-header mt-0">
            <h1>Remove Linked {{ account.provider }} Account</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                    <p class="mt-4">
                        Are you sure you want to delete this <strong>{{ account.provider }}</strong> account?
                    </p>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

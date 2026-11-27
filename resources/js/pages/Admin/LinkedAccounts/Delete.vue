<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
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
        <div class="mp-narrow mp-touch">
            <PageHead :title="`Remove Linked ${account.provider} Account`" />
            <form class="card mx-auto" style="max-inline-size: 36rem" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon ti ti-alert-triangle icon-lg text-danger" aria-hidden="true"></i>
                    <p class="mt-4 mb-0">
                        Are you sure you want to delete this <strong>{{ account.provider }}</strong> account?
                    </p>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

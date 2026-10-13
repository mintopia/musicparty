<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {home} from '@/routes';
import {deleteMethod as confirmRemove, destroy} from '@/routes/linkedaccounts';
import {profile} from '@/routes/user';

const props = defineProps<{account: {id: number; providerName: string}}>();

const form = useForm({});

function submit(): void {
    form.delete(destroy(props.account.id).url);
}
</script>

<template>
    <AppLayout :title="`Remove Linked ${account.providerName} Account`">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a :href="home().url">Home</a></li>
            <li class="breadcrumb-item">
                <Link :href="profile().url">Profile</Link>
            </li>
            <li class="breadcrumb-item active">
                <a :href="confirmRemove(account.id).url">Remove Linked {{ account.providerName }} Account</a>
            </li>
        </template>
        <Head :title="`Remove Linked ${account.providerName} Account`" />
        <div class="page-header mt-0">
            <h1>Remove Linked {{ account.providerName }} Account</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                    <p class="mt-4">
                        Are you sure you want to remove your <strong>{{ account.providerName }}</strong> account?
                    </p>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="profile().url" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">Remove</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

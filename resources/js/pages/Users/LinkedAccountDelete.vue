<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {destroy} from '@/routes/linkedaccounts';
import {profile} from '@/routes/user';

const props = defineProps<{account: {id: number; providerName: string}}>();

const form = useForm({});

function submit(): void {
    form.delete(destroy(props.account.id).url);
}
</script>

<template>
    <AppLayout :title="`Unlink ${account.providerName}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...homeCrumbs(), {label: 'Profile', href: profile().url}, {label: `Unlink ${account.providerName}`}]"
            />
        </template>
        <Head :title="`Unlink ${account.providerName}`" />
        <div class="mp-narrow">
            <PageHead :title="`Unlink ${account.providerName}`" summary="Check what changes before you confirm." />

            <form class="card mp-dialog" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="icon ti ti-alert-triangle icon-lg text-danger"></i>
                    </div>
                    <h2 class="h3 text-center">Unlink {{ account.providerName }}?</h2>
                    <p class="text-secondary mb-0">
                        Are you sure you want to unlink your <strong>{{ account.providerName }}</strong> account?
                    </p>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <Link :href="profile().url" class="btn btn-link">Keep linked</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                            Unlink {{ account.providerName }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

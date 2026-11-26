<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import ModerationBreadcrumbs from '@/components/ModerationBreadcrumbs.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    moderation: {id: number; type_name: string | null; value: string | null};
}>();

const base = `/parties/${props.party.code}/moderation/${props.moderation.id}`;
const form = useForm({});

function submit(): void {
    form.delete(base);
}
</script>

<template>
    <AppLayout :title="`Delete ${moderation.type_name} Filter`">
        <template #breadcrumbs>
            <ModerationBreadcrumbs :party="party">
                <li class="breadcrumb-item active">
                    <a :href="`${base}/delete`">Delete Filter</a>
                </li>
            </ModerationBreadcrumbs>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Delete ${moderation.type_name} Filter`" />
        <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
            <div class="page-header mt-0">
                <h1>Delete {{ moderation.type_name }} Filter</h1>
            </div>

            <div>
                <form class="card" @submit.prevent="submit">
                    <div class="card-status-top bg-danger"></div>
                    <div class="card-body text-center">
                        <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                        <p class="mt-4">
                            Are you sure you want to delete this <strong>{{ moderation.type_name }}</strong> filter for
                            <strong class="font-monospace">{{ moderation.value }}</strong
                            >?
                        </p>
                    </div>
                    <div class="card-footer text-end">
                        <div class="d-flex">
                            <Link :href="`/parties/${party.code}/moderation`" class="btn btn-link">Cancel</Link>
                            <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                                Delete
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

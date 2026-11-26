<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import ModerationBreadcrumbs from '@/components/ModerationBreadcrumbs.vue';
import ModerationForm from '@/components/ModerationForm.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    types: Record<string, string>;
    actions: Record<string, string>;
    moderation: {
        id: number;
        type: string | null;
        type_name: string | null;
        action: string;
        value: string | null;
        enabled: boolean;
        regex: boolean;
        notes: string | null;
    };
}>();

const base = `/parties/${props.party.code}/moderation/${props.moderation.id}`;

const form = useForm({
    type: props.moderation.type ?? '',
    action: props.moderation.action,
    value: props.moderation.value ?? '',
    enabled: props.moderation.enabled,
    regex: props.moderation.regex,
    notes: props.moderation.notes ?? '',
});

function submit(): void {
    form.patch(base);
}
</script>

<template>
    <AppLayout :title="`Edit ${moderation.type_name} Filter`">
        <template #breadcrumbs>
            <ModerationBreadcrumbs :party="party">
                <li class="breadcrumb-item active">
                    <a :href="`${base}/edit`">Edit Filter</a>
                </li>
            </ModerationBreadcrumbs>
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Edit ${moderation.type_name} Filter`" />
        <div class="page-header mt-0">
            <h1>Edit {{ moderation.type_name }} Filter</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <ModerationForm :form="form" :types="types" :actions="actions" />
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="`/parties/${party.code}/moderation`" class="btn btn-link">Cancel</Link>
                        <Link :href="`${base}/delete`" class="btn btn-link text-danger">Delete</Link>
                        <button type="submit" class="btn ms-auto btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

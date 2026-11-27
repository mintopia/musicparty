<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {moderationCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ModerationForm from '@/components/ModerationForm.vue';
import PageHead from '@/components/PageHead.vue';
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
    <AppLayout :title="`Edit ${moderation.type_name} rule`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[
                    ...moderationCrumbs(party),
                    {label: 'Rules', href: base.replace(/\/\d+$/, '')},
                    {label: 'Edit rule'},
                ]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Edit ${moderation.type_name} rule`" />
        <div class="mp-narrow mp-touch">
            <PageHead title="Edit rule" summary="Rules look at every new Request before it reaches the Queue." />
            <form class="card" @submit.prevent="submit">
                <ModerationForm :form="form" :types="types" :actions="actions" />
                <div class="card-footer d-flex gap-2">
                    <Link :href="`${base}/delete`" class="btn btn-outline-danger">
                        <i class="icon ti ti-trash" aria-hidden="true"></i>Delete rule
                    </Link>
                    <Link :href="`/parties/${party.code}/moderation`" class="btn btn-link ms-auto">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save rule</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

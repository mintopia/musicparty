<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {moderationCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    moderation: {id: number; type_name: string | null; value: string | null; action: string};
}>();

const base = `/parties/${props.party.code}/moderation/${props.moderation.id}`;
const form = useForm({});

function submit(): void {
    form.delete(base);
}
</script>

<template>
    <AppLayout :title="`Delete ${moderation.type_name} rule`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[
                    ...moderationCrumbs(party),
                    {label: 'Rules', href: `/parties/${party.code}/moderation`},
                    {label: 'Delete rule'},
                ]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Delete ${moderation.type_name} rule`" />
        <div class="mp-narrow mp-touch">
            <form class="card mx-auto" style="max-inline-size: 28rem" @submit.prevent="submit">
                <div class="card-body">
                    <h1 class="h3 mb-3">Delete this rule?</h1>
                    <p class="mb-0" data-test="delete-copy">
                        The rule <strong>{{ moderation.type_name }} is {{ moderation.value }}</strong> will stop
                        {{ moderation.action === 'flag' ? 'flagging' : 'rejecting' }} Requests. Requests already waiting
                        in Flagged Requests stay there.
                    </p>
                </div>
                <div class="card-footer d-flex gap-2">
                    <Link :href="`/parties/${party.code}/moderation`" class="btn btn-link">Keep rule</Link>
                    <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                        Delete rule
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

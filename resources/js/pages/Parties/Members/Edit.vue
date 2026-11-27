<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PartyPlayer from '@/components/PartyPlayer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState} from '@/types';

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    member: {id: number; nickname: string; role: string; roleCode: string};
    isSelf: boolean;
    roles: {code: string; name: string}[];
}>();

const hints: Record<string, string> = {
    owner: 'Full control of the party, including Settings, members and playback.',
    moderator:
        "Removes, approves and rejects Requests, schedules them and reads AI reviews. Cannot change Settings or roles, or start playback on the owner's Spotify.",
    vip: 'Skips some Request limits.',
    user: 'Requests tracks and votes.',
    banned: 'Cannot make Requests or vote.',
};

const usersUrl = `/parties/${props.party.code}/users`;
const memberUrl = `${usersUrl}/${props.member.id}`;
const form = useForm({role: props.member.roleCode});

function submit(): void {
    form.patch(memberUrl);
}
</script>

<template>
    <AppLayout :title="`Edit ${member.nickname} - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[...partyCrumbs(party), {label: 'Members', href: usersUrl}, {label: member.nickname}]"
            />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Edit ${member.nickname} - ${party.name}`" />
        <div class="mp-page-head">
            <div>
                <h1>Change role for {{ member.nickname }}</h1>
                <p>The role takes effect straight away.</p>
            </div>
        </div>
        <div class="mp-narrow" style="margin: 0">
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div v-if="isSelf" class="alert alert-warning" data-test="self-warning">
                        <div class="d-flex">
                            <div class="p-2">
                                <i class="icon ti ti-alert-triangle" aria-hidden="true"></i>
                            </div>
                            <div>
                                You are editing permissions for yourself. If you change your membership from owner, you
                                will be unable to manage this party.
                            </div>
                        </div>
                    </div>
                    <fieldset>
                        <legend class="form-label">Role</legend>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column mp-roles">
                            <label v-for="role in roles" :key="role.code" class="form-selectgroup-item flex-fill">
                                <input
                                    v-model="form.role"
                                    class="form-selectgroup-input"
                                    type="radio"
                                    name="role"
                                    :value="role.code"
                                />
                                <span class="form-selectgroup-label d-flex align-items-center">
                                    <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                    <span>
                                        <b
                                            >{{ role.name }}
                                            <span v-if="role.code === 'moderator'" class="badge bg-azure-lt ms-1"
                                                >New</span
                                            ></b
                                        >
                                        <span class="d-block">{{ hints[role.code] }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                    <p v-if="form.errors.role" class="invalid-feedback d-block">{{ form.errors.role }}</p>
                </div>
                <div class="card-footer d-flex">
                    <Link :href="memberUrl" class="btn btn-link link-secondary">Cancel</Link>
                    <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save role</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

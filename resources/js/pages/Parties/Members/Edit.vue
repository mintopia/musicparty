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
    owner: 'The user will have full control of the party',
    vip: 'A VIP user. They can bypass some of the request restrictions.',
    user: 'An ordinary user of the party, they can request songs',
    banned: 'This user will not be able to make requests',
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
        <div class="page-header mt-0">
            <h1>Edit {{ member.nickname }}</h1>
        </div>
        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div class="mb-3">
                        <h3 class="card-title">Role</h3>
                        <p class="card-subtitle">A user's role determines what permissions they have for the party.</p>
                        <div v-if="isSelf" class="alert alert-warning" data-test="self-warning">
                            <div class="d-flex">
                                <div class="p-2">
                                    <i class="icon ti ti-alert-triangle"></i>
                                </div>
                                <div>
                                    You are editing permissions for yourself. If you change your membership from owner,
                                    you will be unable to manage this party.
                                </div>
                            </div>
                        </div>
                        <div>
                            <label v-for="role in roles" :key="role.code" class="form-check">
                                <input
                                    v-model="form.role"
                                    class="form-check-input"
                                    type="radio"
                                    name="role"
                                    :value="role.code"
                                />
                                <span class="form-check-label">{{ role.name }}</span>
                                <p class="form-hint">{{ hints[role.code] }}</p>
                            </label>
                        </div>
                    </div>
                    <p v-if="form.errors.role" class="invalid-feedback d-block">{{ form.errors.role }}</p>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="memberUrl" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

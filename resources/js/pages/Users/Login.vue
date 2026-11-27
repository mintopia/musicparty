<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import JoinSteps from '@/components/JoinSteps.vue';
import PartyInviteCard from '@/components/PartyInviteCard.vue';
import type {PartyInvite} from '@/components/PartyInviteCard.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {redirect} from '@/routes/login';

defineProps<{providers: {code: string; name: string}[]; party?: PartyInvite | null}>();
</script>

<template>
    <GuestLayout title="Login">
        <Head title="Login" />
        <template v-if="party">
            <PartyInviteCard :party="party" />
            <JoinSteps :current="1" />
        </template>
        <div class="text-center">
            <p :class="{'mb-2': party}">{{ party ? 'Login to add Requests and vote.' : 'Login to join the party!' }}</p>
            <p v-for="provider in providers" :key="provider.code">
                <a :href="redirect(provider.code).url" :class="`d-block ms-auto btn btn-${provider.code}`">
                    <i :class="`icon ti ti-brand-${provider.code}`"></i>
                    Login with {{ provider.name }}
                </a>
            </p>
            <p v-if="party" class="text-secondary small">
                You'll come straight back to this party. First time here? You'll pick a nickname next.
            </p>
        </div>
    </GuestLayout>
</template>

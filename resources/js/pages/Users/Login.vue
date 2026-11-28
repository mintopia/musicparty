<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import JoinSteps from '@/components/JoinSteps.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {redirect} from '@/routes/login';

defineProps<{providers: {code: string; name: string}[]; fromParty?: boolean}>();
</script>

<template>
    <GuestLayout title="Login">
        <Head title="Login" />
        <JoinSteps v-if="fromParty" :current="1" />
        <div class="text-center">
            <h1 v-if="!fromParty" class="visually-hidden">Login</h1>
            <p v-if="!fromParty">Login to join the party!</p>
            <p v-for="provider in providers" :key="provider.code">
                <a :href="redirect(provider.code).url" :class="`d-block ms-auto btn btn-${provider.code}`">
                    <i :class="`icon ti ti-brand-${provider.code}`"></i>
                    Login with {{ provider.name }}
                </a>
            </p>
        </div>
    </GuestLayout>
</template>

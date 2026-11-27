<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PartyJoinForm from '@/components/PartyJoinForm.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {redirect} from '@/routes/login';

defineProps<{providers: {code: string; name: string}[]}>();
</script>

<template>
    <GuestLayout title="Music Party">
        <Head title="Music Party" />
        <div class="text-center">
            <h2 class="h3 mb-1">Join the party</h2>
            <p class="text-secondary">Enter the four-letter code from the TV.</p>
        </div>
        <div class="mb-4">
            <label class="form-label" for="party-code">Party code</label>
            <PartyJoinForm input-id="party-code" :hint="false" />
        </div>
        <div class="hr-text">or</div>
        <div class="text-center">
            <p class="text-secondary small">Hosting a party? Login to create one.</p>
            <p v-for="provider in providers" :key="provider.code">
                <a :href="redirect(provider.code).url" :class="`d-block ms-auto btn btn-${provider.code}`">
                    <i :class="`icon ti ti-brand-${provider.code}`"></i>
                    Login with {{ provider.name }}
                </a>
            </p>
        </div>
    </GuestLayout>
</template>

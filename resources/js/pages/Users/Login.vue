<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {redirect} from '@/routes/login';

defineProps<{providers: {code: string; name: string}[]}>();
</script>

<template>
    <GuestLayout title="Login">
        <Head title="Login" />
        <div class="text-center">
            <p>Login to join the party!</p>
            <p v-for="provider in providers" :key="provider.code">
                <a :href="redirect(provider.code).url" :class="`d-block ms-auto btn btn-${provider.code}`">
                    <i :class="`icon ti ti-brand-${provider.code}`"></i>
                    Login with {{ provider.name }}
                </a>
            </p>
        </div>
    </GuestLayout>
</template>

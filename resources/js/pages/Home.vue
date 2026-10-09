<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Home" />
    <div class="flex justify-end px-4 pb-4 pt-8">
        <div
            v-if="user"
            class="w-[280px] rounded border border-border bg-surface px-5 pb-6 pt-5 text-center"
        >
            <img :src="user.avatarUrl" alt="" class="mx-auto h-20 w-20 rounded" />
            <div class="mt-3 text-sm font-medium">{{ user.name }}</div>
            <div v-if="user.email" class="mt-1 text-sm text-muted">{{ user.email }}</div>
        </div>
    </div>
</template>

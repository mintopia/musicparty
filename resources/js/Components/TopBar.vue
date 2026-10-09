<script setup>
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';


const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <header
        data-testid="topbar"
        class="flex h-14 items-center justify-between border-b border-border bg-topbar px-4"
    >
        <nav class="text-sm" aria-label="Breadcrumb">
            <a href="/" class="text-primary">Home</a>
        </nav>
        <div v-if="user" class="flex items-center gap-2 text-sm">
            <img :src="user.avatarUrl" alt="" class="h-8 w-8 rounded" />
            <span>{{ user.name }}</span>
        </div>
    </header>
</template>

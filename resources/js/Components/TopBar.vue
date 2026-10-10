<script setup>
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import ColourSchemeToggle from './ColourSchemeToggle.vue';

defineProps({title: {type: String, default: 'Home'}});

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <header
        data-testid="topbar"
        class="flex h-14 items-center justify-between border-b border-border bg-topbar px-4"
    >
        <nav class="text-sm font-semibold" aria-label="Breadcrumb">{{ title }}</nav>
        <div class="flex items-center gap-3 text-sm">
            <ColourSchemeToggle />
            <div v-if="user" class="flex items-center gap-2">
                <img :src="user.avatarUrl" alt="" class="h-8 w-8 rounded" />
                <span>{{ user.name }}</span>
            </div>
        </div>
    </header>
</template>

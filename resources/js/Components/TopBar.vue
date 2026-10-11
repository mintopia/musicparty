<script setup>
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import ColourSchemeToggle from './ColourSchemeToggle.vue';
import Icon from './Icon.vue';
import Breadcrumbs from './ui/Breadcrumbs.vue';

defineProps({crumbs: {type: Array, default: () => []}});
defineEmits(['toggle-sidebar']);

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <header
        data-testid="topbar"
        class="flex h-14 items-center justify-between gap-3 border-b border-border bg-topbar px-4"
    >
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                data-testid="sidebar-toggle"
                aria-label="Open navigation"
                class="rounded p-1 hover:bg-background md:hidden"
                @click="$emit('toggle-sidebar')"
            ><Icon name="menu" /></button>
            <Breadcrumbs :crumbs="crumbs" />
        </div>
        <div class="flex items-center gap-3 text-sm">
            <ColourSchemeToggle />
            <div v-if="user" class="flex items-center gap-2">
                <img :src="user.avatarUrl" alt="" class="h-8 w-8 rounded" />
                <span>{{ user.name }}</span>
            </div>
        </div>
    </header>
</template>

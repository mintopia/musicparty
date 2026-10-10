<script setup>
import SidebarNav from '../Components/SidebarNav.vue';
import TopBar from '../Components/TopBar.vue';
import AppFooter from '../Components/AppFooter.vue';
import MiniNowPlaying from '../Components/MiniNowPlaying.vue';
import MobileTabBar from '../Components/MobileTabBar.vue';
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';

const party = computed(() => usePage().props.party ?? null);
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <aside
            data-testid="sidebar"
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-border bg-sidebar text-sidebar-text md:flex"
        >
            <SidebarNav />
        </aside>

        <div class="flex min-h-screen flex-col md:pl-60">
            <TopBar :title="$page.component" />
            <main class="flex-1" :class="party ? 'pb-32 md:pb-0' : ''">
                <slot />
            </main>
            <div :class="party ? 'hidden md:block' : ''"><AppFooter /></div>
            <template v-if="party">
                <MiniNowPlaying
                    :now-playing="usePage().props.nowPlaying ?? null"
                    :party-code="usePage().props.party?.code ?? ''"
                    :ratable-play="usePage().props.ratablePlay ?? null"
                    :read-only="usePage().props.readOnly ?? false"
                />
                <MobileTabBar :code="party.code" />
            </template>
        </div>
    </div>
</template>

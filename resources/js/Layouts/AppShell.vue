<script setup>
import SidebarNav from '../Components/SidebarNav.vue';
import TopBar from '../Components/TopBar.vue';
import AppFooter from '../Components/AppFooter.vue';
import ImpersonationBanner from '../Components/ImpersonationBanner.vue';
import MiniNowPlaying from '../Components/MiniNowPlaying.vue';
import MobileTabBar from '../Components/MobileTabBar.vue';
import {providePageMeta} from '../composables/usePageMeta.js';
import {pageFallback} from '../lib/pageFallback.js';
import {usePage} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';

const page = usePage();
const party = computed(() => page.props.party ?? null);
const drawerOpen = ref(false);

const meta = providePageMeta();
const resetMeta = () => {
    meta.value = {title: null, crumbs: []};
};
watch(() => page.component, resetMeta, {flush: 'pre'});
watch(() => page.url, () => {
    drawerOpen.value = false;
});

const effectiveMeta = computed(() => {
    const declared = Boolean(meta.value.title) || meta.value.crumbs.length > 0;
    const fallback = pageFallback(page.component, page.props);

    return {
        declared,
        title: meta.value.title ?? fallback.title,
        crumbs: meta.value.crumbs.length ? meta.value.crumbs : fallback.crumbs,
    };
});
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <aside
            data-testid="sidebar"
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-border bg-sidebar text-sidebar-text md:flex"
        >
            <SidebarNav />
        </aside>

        <div v-if="drawerOpen" class="fixed inset-0 z-40 md:hidden" data-testid="sidebar-drawer">
            <div class="absolute inset-0 bg-black/50" data-testid="sidebar-backdrop" @click="drawerOpen = false" />
            <aside class="absolute inset-y-0 left-0 flex w-60 flex-col overflow-y-auto bg-sidebar text-sidebar-text">
                <SidebarNav />
            </aside>
        </div>

        <div class="flex min-h-screen flex-col md:pl-60">
            <TopBar :crumbs="effectiveMeta.crumbs" @toggle-sidebar="drawerOpen = !drawerOpen" />
            <ImpersonationBanner />
            <main class="flex-1" :class="party ? 'pb-32 md:pb-0' : ''">
                <slot :meta="effectiveMeta" />
            </main>
            <div :class="party ? 'hidden md:block' : ''"><AppFooter /></div>
            <template v-if="party">
                <MiniNowPlaying
                    :now-playing="page.props.nowPlaying ?? null"
                    :party-code="page.props.party?.code ?? ''"
                    :ratable-play="page.props.ratablePlay ?? null"
                    :read-only="page.props.readOnly ?? false"
                />
                <MobileTabBar :code="party.code" />
            </template>
        </div>
    </div>
</template>

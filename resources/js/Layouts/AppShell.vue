<script setup>
import SidebarNav from '../Components/SidebarNav.vue';
import TopBar from '../Components/TopBar.vue';
import AppFooter from '../Components/AppFooter.vue';

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
            <main class="flex-1">
                <slot />
            </main>
            <AppFooter />
        </div>
    </div>
</template>

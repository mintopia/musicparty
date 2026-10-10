<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Icon from './Icon.vue';

const props = defineProps({code: {type: String, required: true}});

const page = usePage();
const path = computed(() => page.url.split('?')[0]);

const tabs = computed(() => [
    {label: 'Queue', icon: 'queue', href: `/parties/${props.code}`},
    {label: 'Search', icon: 'search', href: `/parties/${props.code}/search`},
    {label: 'History', icon: 'history', href: `/parties/${props.code}/history`},
    {label: 'Party', icon: 'party', href: `/parties/${props.code}/party`},
]);

const isActive = (href) => path.value === href;
</script>

<template>
    <nav
        data-testid="mobile-tabbar"
        aria-label="Party"
        class="fixed inset-x-0 bottom-0 z-40 flex border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] md:hidden"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :aria-current="isActive(tab.href) ? 'page' : undefined"
            class="flex min-h-14 min-w-11 flex-1 flex-col items-center justify-center gap-0.5 border-t-2 text-xs focus-visible:outline-2 focus-visible:outline-primary"
            :class="isActive(tab.href) ? 'border-primary text-primary' : 'border-transparent text-muted'"
        >
            <Icon :name="tab.icon" />
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AppShell from './AppShell.vue';

const page = usePage();
const path = computed(() => page.url.split('?')[0]);

const items = [
    {label: 'Dashboard', href: '/admin'},
    {label: 'Users', href: '/admin/users'},
    {label: 'Parties', href: '/admin/parties'},
];

const isActive = (href) => (href === '/admin' ? path.value === href : path.value.startsWith(href));
</script>

<template>
    <AppShell>
        <div class="mx-auto max-w-5xl px-4 py-6">
            <nav aria-label="Admin" class="mb-6 flex gap-2 border-b border-border">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="border-b-2 px-3 py-2 text-sm hover:text-primary"
                    :class="isActive(item.href) ? 'border-primary text-primary' : 'border-transparent'"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >{{ item.label }}</Link>
            </nav>
            <slot />
        </div>
    </AppShell>
</template>

<script setup>
import {Link, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const isGuest = computed(() => !page.props.auth.user);
</script>

<template>
    <div class="flex h-14 items-center justify-center text-xl font-semibold text-sidebar-active">
        {{ appName }}
    </div>
    <nav class="flex flex-col gap-0.5 py-2" aria-label="Primary">
        <Link href="/" class="px-4 py-2 text-sm hover:text-sidebar-active">Home</Link>
        <Link v-if="isGuest" href="/login" class="px-4 py-2 text-sm hover:text-sidebar-active">Login</Link>
        <template v-else>
            <Link href="/logout" class="px-4 py-2 text-sm hover:text-sidebar-active">Logout</Link>
        </template>
    </nav>
</template>

<script setup>
import {Link} from '@inertiajs/vue3';

defineProps({crumbs: {type: Array, default: () => []}});
</script>

<template>
    <nav aria-label="Breadcrumb" class="min-w-0 text-sm">
        <ol class="flex min-w-0 items-center gap-2 whitespace-nowrap">
            <li
                v-for="(crumb, index) in crumbs"
                :key="index"
                :class="['items-center gap-2', index < crumbs.length - 2 ? 'hidden md:flex' : 'flex', index === crumbs.length - 1 ? 'min-w-0' : 'shrink-0']"
            >
                <span v-if="index > 0" class="text-muted" aria-hidden="true">/</span>
                <Link v-if="crumb.href && index < crumbs.length - 1" :href="crumb.href" class="text-primary hover:underline">{{ crumb.label }}</Link>
                <span v-else class="truncate font-semibold text-text" :aria-current="index === crumbs.length - 1 ? 'page' : undefined">{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>

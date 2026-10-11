<script setup>
import {Link} from '@inertiajs/vue3';

defineProps({crumbs: {type: Array, default: () => []}});
</script>

<template>
    <nav aria-label="Breadcrumb" class="min-w-0 text-sm">
        <ol class="flex flex-wrap items-center gap-2">
            <li v-for="(crumb, index) in crumbs" :key="index" class="flex items-center gap-2">
                <span v-if="index > 0" class="text-muted" aria-hidden="true">/</span>
                <Link v-if="crumb.href && index < crumbs.length - 1" :href="crumb.href" class="text-primary hover:underline">{{ crumb.label }}</Link>
                <span v-else class="font-semibold text-text" :aria-current="index === crumbs.length - 1 ? 'page' : undefined">{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>

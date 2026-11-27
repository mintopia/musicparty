<script setup lang="ts">
import type {BreadcrumbItem} from '@/lib/breadcrumbs';

defineProps<{items: BreadcrumbItem[]}>();
</script>

<template>
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <template v-for="(item, index) in items" :key="index">
                <li v-if="index === items.length - 1" class="breadcrumb-item active" aria-current="page">
                    {{ item.label }}
                </li>
                <li v-else class="breadcrumb-item">
                    <a v-if="item.href" :href="item.href">{{ item.label }}</a>
                    <template v-else>{{ item.label }}</template>
                </li>
            </template>
        </ol>
    </nav>
</template>

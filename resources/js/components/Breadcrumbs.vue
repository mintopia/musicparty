<script setup lang="ts">
import {computed, ref} from 'vue';
import type {BreadcrumbItem} from '@/lib/breadcrumbs';

const props = defineProps<{items: BreadcrumbItem[]}>();

const expanded = ref(false);
const collapsible = computed(() => props.items.length > 2);
const hiddenPath = computed(() =>
    props.items
        .slice(1, -1)
        .map((item) => item.label)
        .join(', '),
);

function middleClass(index: number): string | undefined {
    const isMiddle = index > 0 && index < props.items.length - 1;
    return collapsible.value && isMiddle && !expanded.value ? 'd-none d-sm-block' : undefined;
}
</script>

<template>
    <nav class="mp-breadcrumbs" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <template v-for="(item, index) in items" :key="index">
                <li v-if="index === items.length - 1" class="breadcrumb-item active" aria-current="page">
                    {{ item.label }}
                </li>
                <li v-else class="breadcrumb-item" :class="middleClass(index)">
                    <a v-if="item.href" :href="item.href">{{ item.label }}</a>
                    <template v-else>{{ item.label }}</template>
                </li>
                <li
                    v-if="index === 0 && collapsible && !expanded"
                    class="breadcrumb-item d-sm-none"
                    data-test="crumb-toggle"
                >
                    <button
                        type="button"
                        class="btn btn-link mp-crumb-toggle"
                        :aria-label="`Show full path: ${hiddenPath}`"
                        @click="expanded = true"
                    >
                        <i class="icon ti ti-dots" aria-hidden="true"></i>
                    </button>
                </li>
            </template>
        </ol>
    </nav>
</template>

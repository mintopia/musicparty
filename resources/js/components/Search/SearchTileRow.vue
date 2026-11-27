<script setup lang="ts">
import {onBeforeUnmount, onMounted, onUpdated, ref} from 'vue';

defineProps<{round?: boolean}>();

const row = ref<HTMLElement | null>(null);
let observer: ResizeObserver | null = null;

function hideOverflow(): void {
    const el = row.value;
    if (!el) {
        return;
    }
    const columns = getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length;
    Array.from(el.children).forEach((child, index) => {
        (child as HTMLElement).inert = index >= columns;
    });
}

onMounted(() => {
    hideOverflow();
    if (typeof ResizeObserver !== 'undefined' && row.value) {
        observer = new ResizeObserver(hideOverflow);
        observer.observe(row.value);
    }
});

onUpdated(hideOverflow);
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="row" class="search-tile-row" :class="{'search-tile-row-round': round}">
        <slot />
    </div>
</template>

<style scoped>
.search-tile-row {
    --tile-min: 132px;

    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(var(--tile-min), 1fr));
    grid-template-rows: auto;
    grid-auto-rows: 0;
    column-gap: 1rem;
    row-gap: 0;
    overflow: hidden;
    padding-bottom: 0.25rem;
}

.search-tile-row-round {
    --tile-min: 104px;
}

@media (min-width: 768px) {
    .search-tile-row {
        --tile-min: 150px;
    }

    .search-tile-row-round {
        --tile-min: 130px;
    }
}
</style>

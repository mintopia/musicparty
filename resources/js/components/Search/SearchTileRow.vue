<script setup lang="ts">
defineProps<{round?: boolean}>();
</script>

<template>
    <div class="search-tile-row" :class="{'search-tile-row-round': round}">
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

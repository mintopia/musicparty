<script setup lang="ts">
defineProps<{
    label: string;
    hint?: string;
    for?: string;
    error?: string;
    stacked?: boolean;
    wide?: boolean;
}>();
</script>

<template>
    <div
        class="setting-row"
        :class="{'setting-row-stacked': stacked, 'setting-row-wide': wide}"
        data-test="setting-row"
    >
        <div class="setting-text">
            <label class="setting-label" :for="$props.for">{{ label }}</label>
            <p v-if="hint" class="setting-hint">{{ hint }}</p>
        </div>
        <div class="setting-control">
            <slot />
            <p v-if="error" class="invalid-feedback d-block mb-0 mt-1" data-test="setting-error">{{ error }}</p>
        </div>
    </div>
</template>

<style scoped>
.setting-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 18rem);
    align-items: center;
    gap: 0.25rem 1.5rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--tblr-border-color);
}

.setting-row-stacked {
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
}

.setting-row-wide {
    grid-template-columns: auto minmax(0, 1fr);
}

.setting-row-wide .setting-control {
    flex-wrap: nowrap;
}

.setting-label {
    margin: 0;
    font-weight: 500;
    color: var(--tblr-body-color);
}

.setting-hint {
    max-width: 65ch;
    margin: 0.125rem 0 0;
    color: var(--tblr-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
}

.setting-control {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    min-width: 0;
}

.setting-control > :deep(.form-control),
.setting-control > :deep(.form-select) {
    width: 100%;
}

.setting-row-stacked .setting-control {
    justify-content: flex-start;
}

@media (max-width: 575.98px) {
    .setting-row {
        grid-template-columns: minmax(0, 1fr);
        align-items: start;
    }

    .setting-control {
        justify-content: flex-start;
    }

    .setting-label {
        display: flex;
        align-items: center;
        min-block-size: 44px;
    }
}
</style>

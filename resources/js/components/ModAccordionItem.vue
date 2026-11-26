<script setup lang="ts">
const props = defineProps<{
    id: string;
    name: string;
    description?: string | null;
    open: boolean;
}>();

const emit = defineEmits<{'update:open': [value: boolean]}>();

function toggle(): void {
    emit('update:open', !props.open);
}
</script>

<template>
    <div class="accordion-item" data-test="mod">
        <h2 class="accordion-header d-flex align-items-center">
            <button
                type="button"
                class="accordion-button flex-grow-1"
                :class="{collapsed: !open}"
                :aria-expanded="open"
                :aria-controls="`${id}-body`"
                data-test="mod-toggle"
                @click="toggle"
            >
                <span class="d-flex flex-column">
                    <span class="fw-bold">{{ name }}</span>
                    <span v-if="description" class="text-secondary small fw-normal">{{ description }}</span>
                </span>
            </button>
            <div class="px-3 flex-shrink-0">
                <slot name="status" />
            </div>
        </h2>
        <div :id="`${id}-body`" class="accordion-collapse collapse" :class="{show: open}">
            <div class="accordion-body">
                <slot />
            </div>
        </div>
    </div>
</template>

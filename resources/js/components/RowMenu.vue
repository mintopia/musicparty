<script setup lang="ts">
import {onBeforeUnmount, onMounted, ref} from 'vue';

defineProps<{label: string}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

function close(): void {
    open.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="mp-menu-anchor" data-test="row-menu">
        <button
            type="button"
            class="btn btn-icon btn-ghost-secondary"
            :aria-label="label"
            :aria-expanded="open"
            data-test="row-menu-toggle"
            @click="open = !open"
        >
            <i class="icon ti ti-dots" aria-hidden="true"></i>
        </button>
        <div v-if="open" class="dropdown-menu dropdown-menu-end show" data-test="row-menu-items" @click="close">
            <slot />
        </div>
    </div>
</template>

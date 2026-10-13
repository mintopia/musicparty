<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import type {SharedProps} from '@/types';

const props = withDefaults(defineProps<{autoDismissMs?: number}>(), {autoDismissMs: 0});

const page = usePage<SharedProps>();
const dismissed = ref(new Set<string>());
const timers: ReturnType<typeof setTimeout>[] = [];

const levels = [
    {key: 'success', cls: 'text-bg-success'},
    {key: 'error', cls: 'text-bg-danger'},
    {key: 'info', cls: 'text-bg-info'},
    {key: 'warning', cls: 'text-bg-warning'},
] as const;

const toasts = computed(() =>
    levels.flatMap(({key, cls}) => {
        const text = page.props.flash[key];
        return text && !dismissed.value.has(key) ? [{key, cls, text}] : [];
    }),
);

function dismiss(key: string): void {
    dismissed.value = new Set([...dismissed.value, key]);
}

watch(
    () => JSON.stringify(page.props.flash),
    () => {
        dismissed.value = new Set();
        if (props.autoDismissMs > 0) {
            levels.forEach(({key}) => {
                if (page.props.flash[key]) {
                    timers.push(setTimeout(() => dismiss(key), props.autoDismissMs));
                }
            });
        }
    },
    {immediate: true},
);

onBeforeUnmount(() => timers.forEach(clearTimeout));
</script>

<template>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div
            v-for="toast in toasts"
            :key="toast.key"
            class="toast show align-items-center border-0"
            :class="toast.cls"
            role="alert"
        >
            <div class="d-flex">
                <div class="toast-body">{{ toast.text }}</div>
                <button
                    type="button"
                    class="btn-close me-2 m-auto"
                    aria-label="close"
                    @click="dismiss(toast.key)"
                ></button>
            </div>
        </div>
    </div>
</template>

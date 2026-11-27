<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import type {SharedProps} from '@/types';

const props = withDefaults(
    defineProps<{
        autoDismissMs?: number;
        message?: string | null;
        variant?: 'success' | 'danger' | 'info' | 'warning' | 'dark';
        actionLabel?: string;
    }>(),
    {autoDismissMs: 0, message: undefined, variant: 'danger', actionLabel: undefined},
);
const emit = defineEmits<{dismiss: []; action: []}>();

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
    props.message !== undefined
        ? props.message
            ? [{key: 'message', cls: `text-bg-${props.variant}`, text: props.message}]
            : []
        : levels.flatMap(({key, cls}) => {
              const text = page.props.flash[key];
              return text && !dismissed.value.has(key) ? [{key, cls, text}] : [];
          }),
);

function dismiss(key: string): void {
    if (key === 'message') {
        emit('dismiss');
        return;
    }
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
                <div class="toast-body">
                    <slot v-if="toast.key === 'message'">{{ toast.text }}</slot>
                    <template v-else>{{ toast.text }}</template>
                </div>
                <button
                    v-if="toast.key === 'message' && actionLabel"
                    type="button"
                    class="mp-toast-action"
                    data-test="toast-action"
                    @click="emit('action')"
                >
                    {{ actionLabel }}
                </button>
                <button
                    type="button"
                    class="btn-close me-2 m-auto"
                    :class="{'btn-close-white': toast.cls !== 'text-bg-warning' && toast.cls !== 'text-bg-info'}"
                    aria-label="close"
                    @click="dismiss(toast.key)"
                ></button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mp-toast-action {
    align-self: center;
    background: none;
    border: 0;
    color: inherit;
    font-weight: 600;
    text-decoration: underline;
    padding: 0.5rem 0.75rem;
    min-block-size: 44px;
}

.mp-toast-action:focus-visible {
    outline: 2px solid currentcolor;
    outline-offset: -2px;
}
</style>

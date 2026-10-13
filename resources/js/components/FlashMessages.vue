<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import type {SharedProps} from '@/types';

const props = defineProps<{centered?: boolean}>();

const page = usePage<SharedProps>();
const dismissed = ref(new Set<string>());

const levels = [
    {key: 'success', cls: 'alert-success'},
    {key: 'error', cls: 'alert-danger'},
    {key: 'info', cls: 'alert-info'},
    {key: 'warning', cls: 'alert-warning'},
] as const;

const messages = computed(() =>
    levels.flatMap(({key, cls}) => {
        const text = page.props.flash[key];
        return text && !dismissed.value.has(key) ? [{key, cls, text}] : [];
    }),
);

watch(
    () => JSON.stringify(page.props.flash),
    () => {
        dismissed.value = new Set();
    },
);

function dismiss(key: string): void {
    dismissed.value = new Set([...dismissed.value, key]);
}
</script>

<template>
    <div
        v-for="message in messages"
        :key="message.key"
        class="alert alert-important"
        :class="[message.cls, props.centered ? 'text-center' : 'alert-dismissible']"
        role="alert"
    >
        {{ message.text }}
        <a v-if="!props.centered" class="btn-close" aria-label="close" @click.prevent="dismiss(message.key)"></a>
    </div>
</template>

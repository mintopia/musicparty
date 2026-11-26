<script setup lang="ts">
import {onBeforeUnmount, ref} from 'vue';

const props = defineProps<{value: string; label: string}>();

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

function fallbackCopy(text: string): boolean {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try {
        return document.execCommand('copy');
    } finally {
        document.body.removeChild(area);
    }
}

async function copy(): Promise<void> {
    let ok = false;
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.value);
            ok = true;
        }
    } catch {
        ok = false;
    }
    if (!ok) {
        ok = fallbackCopy(props.value);
    }
    if (!ok) {
        return;
    }
    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <button
        type="button"
        class="btn btn-sm btn-icon btn-ghost-secondary flex-shrink-0"
        data-test="copy-button"
        :aria-label="label"
        :title="copied ? 'Copied' : label"
        @click="copy"
    >
        <i class="icon ti" :class="copied ? 'ti-check text-success' : 'ti-copy'" aria-hidden="true"></i>
        <span class="visually-hidden" role="status" aria-live="polite">{{ copied ? 'Copied' : '' }}</span>
    </button>
</template>

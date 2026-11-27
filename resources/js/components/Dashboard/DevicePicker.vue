<script setup lang="ts">
import {useId} from 'vue';
import type {DashboardDevice} from '@/types';

defineProps<{
    devices: DashboardDevice[];
    modelValue: string | null;
    host: string;
    locked?: boolean;
    refreshing?: boolean;
}>();
const emit = defineEmits<{'update:modelValue': [id: string]; refresh: []}>();

const name = `dev-${useId()}`;
</script>

<template>
    <div v-if="devices.length === 0" data-test="no-devices">
        <div class="text-secondary small mb-2">No devices found on {{ host }}'s Spotify.</div>
        <button
            type="button"
            class="btn btn-outline-secondary w-100 btn-refresh"
            :disabled="refreshing"
            data-test="refresh-devices"
            @click="emit('refresh')"
        >
            <span v-if="refreshing" class="spinner-border spinner-border-sm me-1" role="status"></span>
            <i v-else class="ti ti-refresh me-1"></i> Refresh devices
        </button>
    </div>
    <div v-else class="form-selectgroup form-selectgroup-boxes dev-grid dev" data-test="device-picker">
        <label v-for="device in devices" :key="device.id" class="form-selectgroup-item flex-fill">
            <input
                type="radio"
                :name="name"
                class="form-selectgroup-input"
                :value="device.id"
                :checked="modelValue === device.id"
                :disabled="locked || device.isRestricted"
                :data-test="`device-${device.id}`"
                @change="emit('update:modelValue', device.id)"
            />
            <div class="form-selectgroup-label d-flex align-items-center">
                <span class="form-selectgroup-check me-3"></span>
                <i class="ti ti-dev" :class="device.icon"></i>
                <div class="lh-sm">
                    <div class="fw-medium">{{ device.name }}</div>
                    <div class="text-secondary small">{{ device.label }}</div>
                </div>
            </div>
        </label>
    </div>
</template>

<style scoped>
.dev-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 0;
}
.dev-grid .form-selectgroup-item {
    margin: 0;
}
.dev-grid .form-selectgroup-check {
    display: none;
}
.dev .form-selectgroup-label {
    padding: 0.55rem 0.7rem;
    text-align: left;
    background: var(--mp-deck-surface);
    color: var(--mp-deck-text);
    border-color: var(--mp-deck-border);
}
.dev .ti-dev {
    font-size: 1.5rem;
    margin-right: 0.75rem;
}
.dev {
    --tblr-secondary: var(--mp-deck-muted);
    --tblr-muted: var(--mp-deck-muted);
}
.dev .form-selectgroup-input:checked + .form-selectgroup-label {
    background: color-mix(in srgb, var(--tblr-primary) 22%, transparent);
    border-color: var(--tblr-primary);
}
.dev .form-selectgroup-input:disabled + .form-selectgroup-label {
    opacity: 0.5;
}
.btn-refresh {
    --tblr-btn-color: var(--mp-deck-text);
    --tblr-btn-border-color: var(--mp-deck-border-strong);
    --tblr-btn-hover-color: var(--mp-deck-text);
    --tblr-btn-hover-bg: var(--mp-deck-raised);
    --tblr-btn-hover-border-color: var(--mp-deck-border-strong);
}
</style>

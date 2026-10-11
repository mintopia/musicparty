<script setup>
defineProps({
    modelValue: {type: Boolean, default: false},
    label: {type: String, default: null},
    disabled: {type: Boolean, default: false},
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-3 text-sm" :class="disabled ? 'cursor-not-allowed opacity-50' : ''">
        <button
            type="button"
            role="switch"
            :aria-checked="modelValue ? 'true' : 'false'"
            :aria-label="label"
            :disabled="disabled"
            class="relative h-5 w-9 shrink-0 rounded-full border border-border transition-colors"
            :class="modelValue ? 'border-primary bg-primary' : 'bg-background'"
            @click="$emit('update:modelValue', !modelValue)"
        >
            <span
                class="absolute left-0.5 top-0.5 h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"
                :class="modelValue ? 'translate-x-4' : 'bg-muted'"
            />
        </button>
        <span v-if="label">{{ label }}</span>
    </label>
</template>

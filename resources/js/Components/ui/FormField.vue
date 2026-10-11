<script setup>
defineProps({
    label: {type: String, required: true},
    for: {type: String, default: null},
    required: {type: Boolean, default: false},
    hint: {type: String, default: null},
    error: {type: String, default: null},
});
</script>

<template>
    <div class="mb-5">
        <label :for="$props.for" class="mb-2 block text-sm font-medium">
            {{ label }}<span v-if="required" class="ml-0.5 text-danger" aria-hidden="true">*</span>
        </label>
        <slot />
        <p v-if="hint" class="mt-1 text-xs text-muted">{{ hint }}</p>
        <p v-if="error" role="alert" class="mt-1 text-sm text-danger">{{ error }}</p>
    </div>
</template>

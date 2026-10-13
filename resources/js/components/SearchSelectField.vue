<script setup lang="ts">
defineProps<{
    name: string;
    property: string;
    options: Record<string, unknown>[];
    valueKey: string;
    labelKey: string;
    error?: string | null;
}>();

const model = defineModel<string | number | null>();
</script>

<template>
    <div class="mb-3">
        <label class="form-label" :for="property">{{ name }}</label>
        <select :id="property" v-model="model" class="form-select" :class="{'is-invalid': error}" :name="property">
            <option value="">All</option>
            <option v-for="(option, index) in options" :key="index" :value="option[valueKey]">
                {{ option[labelKey] }}
            </option>
        </select>
        <div v-if="error" class="invalid-feedback">{{ error }}</div>
    </div>
</template>

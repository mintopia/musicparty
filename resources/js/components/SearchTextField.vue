<script setup lang="ts">
defineProps<{name: string; property: string; error?: string | null}>();

const model = defineModel<string | null>();
</script>

<template>
    <div class="mb-3">
        <label class="form-label" :for="property">{{ name }}</label>
        <input
            :id="property"
            v-model="model"
            class="form-control"
            :class="{'is-invalid': error}"
            type="text"
            :name="property"
            :placeholder="name"
        />
        <div v-if="error" class="invalid-feedback">{{ error }}</div>
    </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{label?: string}>(), {label: 'Schedule requests for (party time, optional)'});
const model = defineModel<string>({default: ''});
</script>

<template>
    <div class="mb-3" data-test="schedule-control">
        <label class="form-label" for="scheduled-for">{{ label }}</label>
        <input id="scheduled-for" v-model="model" type="datetime-local" class="form-control" />
    </div>
</template>

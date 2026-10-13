<script setup lang="ts">
const model = defineModel<string>({default: ''});
</script>

<template>
    <div class="mb-3" data-test="schedule-control">
        <label class="form-label" for="scheduled-for">Schedule requests for (party time, optional)</label>
        <input id="scheduled-for" v-model="model" type="datetime-local" class="form-control" />
    </div>
</template>

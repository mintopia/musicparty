<script setup lang="ts">
withDefaults(defineProps<{rating: number | null | undefined; buttonClass?: string}>(), {
    buttonClass: 'btn-ghost-light',
});
const emit = defineEmits<{rate: [value: number]}>();
</script>

<template>
    <div class="d-inline-flex align-items-center gap-2" data-test="rate-control">
        <button type="button" :class="['btn btn-icon', buttonClass]" aria-label="Rate down" @click="emit('rate', -1)">
            <i class="icon ti ti-thumb-down"></i>
        </button>
        <span data-test="rating">{{ rating ?? 0 }}</span>
        <button type="button" :class="['btn btn-icon', buttonClass]" aria-label="Rate up" @click="emit('rate', 1)">
            <i class="icon ti ti-thumb-up"></i>
        </button>
    </div>
</template>

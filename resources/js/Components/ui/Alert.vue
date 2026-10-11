<script setup>
import {computed} from 'vue';
import Icon from '../Icon.vue';

const props = defineProps({variant: {type: String, default: 'info'}});

const classes = {
    success: 'border-accent bg-accent/10 text-text',
    danger: 'border-danger bg-danger/10 text-text',
    info: 'border-primary bg-primary/10 text-text',
    warning: 'border-[#f59f00] bg-[#f59f00]/10 text-text',
};
const iconColour = {success: 'text-accent', danger: 'text-danger', info: 'text-primary', warning: 'text-[#f59f00]'};
const role = computed(() => (props.variant === 'danger' || props.variant === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div :role="role" :data-variant="variant" class="flex items-start gap-3 rounded border border-l-4 px-4 py-3 text-sm" :class="classes[variant] ?? classes.info">
        <Icon :name="variant === 'success' ? 'check' : 'alertTriangle'" :class="iconColour[variant]" />
        <div class="flex-1"><slot /></div>
    </div>
</template>

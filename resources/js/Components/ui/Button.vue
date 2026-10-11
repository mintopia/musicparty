<script setup>
import {buttonClasses} from './buttonClasses.js';

defineProps({
    variant: {type: String, default: 'primary'},
    type: {type: String, default: 'button'},
    disabled: {type: Boolean, default: false},
});
</script>

<template>
    <button :type="type" :disabled="disabled" :class="buttonClasses(variant, disabled)">
        <slot name="icon" />
        <slot />
    </button>
</template>

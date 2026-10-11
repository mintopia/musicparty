<script setup>
import {Link} from '@inertiajs/vue3';
import {buttonClasses} from './buttonClasses.js';

defineProps({
    href: {type: String, required: true},
    variant: {type: String, default: 'primary'},
    disabled: {type: Boolean, default: false},
});
</script>

<template>
    <Link
        :href="href"
        :class="buttonClasses(variant, disabled)"
        :aria-disabled="disabled ? 'true' : undefined"
        :tabindex="disabled ? -1 : undefined"
    >
        <slot name="icon" />
        <slot />
    </Link>
</template>

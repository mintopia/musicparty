<script setup lang="ts">
import {computed} from 'vue';

const props = defineProps<{image?: string | null}>();

const imageVar = computed(() => (props.image ? `url("${props.image.replace(/["\\]/g, '\\$&')}")` : 'none'));
</script>

<template>
    <div class="now-playing-backdrop" :style="{'--np-image': imageVar}">
        <slot />
    </div>
</template>

<style scoped>
.now-playing-backdrop {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    background-color: #16181d;
    color: #fff;
}

.now-playing-backdrop::before {
    content: '';
    position: absolute;
    inset: -40px;
    z-index: -2;
    background: var(--np-image) center / cover no-repeat;
    filter: blur(36px) saturate(1.2);
    opacity: 0.55;
}

.now-playing-backdrop::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(rgba(10, 10, 14, 0.55), rgba(10, 10, 14, 0.8));
}
</style>

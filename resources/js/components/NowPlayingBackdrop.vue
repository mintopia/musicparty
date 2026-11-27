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
    overflow: hidden;
    background-color: #000;
    background-image: linear-gradient(rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.9)), var(--np-image);
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    color: #fff;
}
</style>

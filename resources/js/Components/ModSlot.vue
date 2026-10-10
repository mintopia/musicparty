<script setup>
import {computed, inject, unref} from 'vue';
import modSlots from '../lib/modSlots';

defineOptions({inheritAttrs: false});

const props = defineProps({
    name: {type: String, required: true},
    registry: {type: Object, default: null},
});

const enabledMods = inject('enabledMods', []);
const contributions = computed(() => (props.registry ?? modSlots).contributions(props.name, unref(enabledMods) ?? []));
</script>

<template>
    <component
        :is="entry.component"
        v-for="entry in contributions"
        :key="entry.modId"
        v-bind="$attrs"
        :data-mod-slot="name"
        :data-mod-id="entry.modId"
    />
</template>

<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref} from 'vue';

const props = defineProps({
    modelValue: {type: [String, Number], default: null},
    options: {type: Array, required: true},
    id: {type: String, default: null},
    disabled: {type: Boolean, default: false},
});
const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const open = ref(false);
const active = ref(0);

const selected = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);

const choose = (option) => {
    emit('update:modelValue', option.value);
    open.value = false;
};

const openList = async () => {
    if (props.disabled) {
        return;
    }
    active.value = Math.max(0, props.options.findIndex((option) => option.value === props.modelValue));
    open.value = true;
    await nextTick();
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!open.value) {
            openList();
            return;
        }
        const step = event.key === 'ArrowDown' ? 1 : -1;
        active.value = (active.value + step + props.options.length) % props.options.length;
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        if (open.value) {
            choose(props.options[active.value]);
        } else {
            openList();
        }
    } else if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        open.value = false;
    }
};

const onOutside = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative inline-block" data-testid="select-field">
        <button
            :id="id"
            type="button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open ? 'true' : 'false'"
            :disabled="disabled"
            class="flex min-w-40 items-center justify-between gap-2 rounded border border-border bg-background px-3 py-2 text-left text-sm disabled:cursor-not-allowed disabled:opacity-50"
            @click="open ? (open = false) : openList()"
            @keydown="onKeydown"
        >
            <span>{{ selected?.label ?? '' }}</span>
            <span aria-hidden="true" class="text-muted">&#9662;</span>
        </button>
        <ul
            v-if="open"
            role="listbox"
            class="absolute z-10 mt-1 max-h-60 w-full min-w-40 overflow-auto rounded border border-border bg-surface py-1 text-sm shadow"
        >
            <li
                v-for="(option, index) in options"
                :key="option.value"
                role="option"
                :aria-selected="option.value === modelValue ? 'true' : 'false'"
                class="cursor-pointer px-3 py-2"
                :class="index === active ? 'bg-background' : ''"
                @mousedown.prevent="choose(option)"
                @mouseenter="active = index"
            >
                {{ option.label }}
            </li>
        </ul>
    </div>
</template>

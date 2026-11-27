<script setup lang="ts">
import {onBeforeUnmount, ref, watch} from 'vue';

export interface SearchChip {
    key: string;
    label: string;
    count?: number;
    pressed: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        label?: string;
        resultCount?: number;
        total?: number;
        noun?: string;
        chips?: SearchChip[];
        hint?: string;
        debounceMs?: number;
    }>(),
    {placeholder: 'Search', label: 'Search', noun: '', chips: () => [], debounceMs: 300},
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [value: string];
    'toggle-chip': [key: string];
}>();

const draft = ref(props.modelValue);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.modelValue,
    (value) => {
        draft.value = value;
    },
);

function clearTimer(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
}

function onInput(event: Event): void {
    draft.value = (event.target as HTMLInputElement).value;
    clearTimer();
    timer = setTimeout(() => {
        timer = null;
        emit('update:modelValue', draft.value);
    }, props.debounceMs);
}

function onSubmit(): void {
    clearTimer();
    if (draft.value !== props.modelValue) {
        emit('update:modelValue', draft.value);
    }
    emit('submit', draft.value);
}

function countText(): string {
    if (props.resultCount === undefined) {
        return '';
    }
    const noun = props.noun ? ` ${props.noun}` : '';
    return props.total !== undefined && props.total !== props.resultCount
        ? `${props.resultCount} of ${props.total}${noun}`
        : `${props.resultCount}${noun}`;
}

onBeforeUnmount(clearTimer);
</script>

<template>
    <div class="mp-searchbar" data-test="searchbar">
        <div class="mp-searchbar-row">
            <form class="mp-search" role="search" @submit.prevent="onSubmit">
                <i class="icon ti ti-search" aria-hidden="true"></i>
                <input
                    class="form-control"
                    type="search"
                    :value="draft"
                    :placeholder="placeholder"
                    :aria-label="label"
                    data-test="search-input"
                    @input="onInput"
                />
                <span v-if="resultCount !== undefined" class="mp-search-count" data-test="search-count">{{ countText() }}</span>
            </form>
            <slot />
        </div>
        <div v-if="chips.length" class="mp-chips" role="group" aria-label="Filters">
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                class="mp-chip"
                :aria-pressed="chip.pressed"
                data-test="search-chip"
                @click="emit('toggle-chip', chip.key)"
            >
                {{ chip.label }}
                <span v-if="chip.count !== undefined" class="mp-num">{{ chip.count }}</span>
            </button>
        </div>
        <p v-if="hint || $slots.hint" class="mp-hint"><slot name="hint">{{ hint }}</slot></p>
    </div>
</template>

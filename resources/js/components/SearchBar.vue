<script setup lang="ts">
import {onBeforeUnmount, ref, watch} from 'vue';

export interface SearchChipGroup {
    key: string;
    label: string;
    chips: SearchChip[];
}

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
        groups?: SearchChipGroup[];
        hint?: string;
        hintLead?: string;
        hintTokens?: string[];
        hintTrail?: string;
        debounceMs?: number;
    }>(),
    {
        placeholder: 'Search',
        label: 'Search',
        noun: '',
        chips: () => [],
        groups: () => [],
        hintTokens: () => [],
        debounceMs: 300,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [value: string];
    'toggle-chip': [key: string, groupKey?: string];
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
                <span v-if="resultCount !== undefined" class="mp-search-count" data-test="search-count">{{
                    countText()
                }}</span>
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
                <i v-if="chip.pressed" class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        <div v-if="groups.length" class="mp-chips-row">
            <div v-for="group in groups" :key="group.key" class="mp-chips" role="group" :aria-label="group.label">
                <span class="mp-chip-label">{{ group.label }}</span>
                <button
                    v-for="chip in group.chips"
                    :key="chip.key"
                    type="button"
                    class="mp-chip"
                    :aria-pressed="chip.pressed"
                    data-test="search-chip"
                    @click="emit('toggle-chip', chip.key, group.key)"
                >
                    {{ chip.label }}
                    <span v-if="chip.count !== undefined" class="mp-num">{{ chip.count }}</span>
                    <i v-if="chip.pressed" class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <p v-if="hintTokens.length" class="mp-hint" data-test="search-hint">
            {{ hintLead }}
            <template v-for="(token, index) in hintTokens" :key="token"
                ><code>{{ token }}</code
                ><template v-if="index < hintTokens.length - 2">, </template
                ><template v-else-if="index === hintTokens.length - 2"> or </template></template
            >{{ hintTrail }}
        </p>
        <p v-else-if="hint || $slots.hint" class="mp-hint">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>

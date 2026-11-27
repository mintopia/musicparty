<script setup lang="ts">
import {computed, ref} from 'vue';

export interface PickerOption {
    value: string;
    label: string;
    meta?: string;
}

const props = defineProps<{
    id: string;
    options: PickerOption[];
    label: string;
    invalid?: boolean;
}>();

const model = defineModel<string>({required: true});
const open = ref(false);
const query = ref('');

const selected = computed(() => props.options.find((option) => option.value === model.value));
const visible = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return needle
        ? props.options.filter((option) => `${option.label} ${option.meta ?? ''}`.toLowerCase().includes(needle))
        : props.options;
});

function choose(option: PickerOption): void {
    model.value = option.value;
    open.value = false;
    query.value = '';
}
</script>

<template>
    <div class="mp-pick" data-test="option-picker">
        <button
            :id="id"
            type="button"
            class="form-select"
            :class="{'is-invalid': invalid}"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="mp-pick-label">{{ selected?.label ?? '' }}</span>
            <small v-if="selected?.meta">{{ selected.meta }}</small>
        </button>
        <div v-if="open" class="dropdown-menu show mp-pick-menu" role="listbox" :aria-label="label">
            <div class="mp-pick-head">
                <input
                    v-model="query"
                    type="text"
                    class="form-control"
                    role="combobox"
                    aria-expanded="true"
                    :placeholder="`Search ${label.toLowerCase()}`"
                    :aria-label="`Search ${label.toLowerCase()}`"
                />
            </div>
            <div class="mp-pick-list">
                <button
                    v-for="option in visible"
                    :key="option.value"
                    type="button"
                    class="mp-opt mp-opt-compact"
                    role="option"
                    data-test="picker-option"
                    :aria-selected="option.value === model"
                    @click="choose(option)"
                >
                    <span class="mp-opt-text"><span class="mp-opt-name">{{ option.label }}</span></span>
                    <small v-if="option.meta" class="mp-opt-meta">{{ option.meta }}</small>
                    <i v-if="option.value === model" class="icon ti ti-check" aria-hidden="true"></i>
                </button>
                <p v-if="visible.length === 0" class="mp-pick-empty mb-0">Nothing matches.</p>
            </div>
        </div>
    </div>
</template>

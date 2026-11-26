<script setup lang="ts">
import axios from 'axios';
import {ref, watch} from 'vue';

interface UserOption {
    id: number;
    nickname: string;
}

const props = defineProps<{
    id: string;
    searchUrl: string;
    initialLabel?: string | null;
    invalid?: boolean;
    required?: boolean;
}>();

const model = defineModel<number | null>({default: null});

const text = ref(model.value !== null ? (props.initialLabel ?? `User #${model.value}`) : '');
const options = ref<UserOption[]>([]);
const open = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

async function search(term: string): Promise<void> {
    const response = await axios.get<{data: UserOption[]}>(props.searchUrl, {params: {q: term}});
    options.value = response.data.data;
}

function onInput(event: Event): void {
    text.value = (event.target as HTMLInputElement).value;
    model.value = null;
    open.value = true;
    if (timer !== null) {
        clearTimeout(timer);
    }
    timer = setTimeout(() => void search(text.value), 250);
}

function onFocus(): void {
    open.value = true;
    void search(model.value === null ? text.value : '');
}

function choose(option: UserOption): void {
    model.value = option.id;
    text.value = option.nickname;
    open.value = false;
}

function clear(): void {
    model.value = null;
    text.value = '';
    open.value = false;
}

watch(
    () => model.value,
    (value) => {
        if (value === null && !open.value) {
            text.value = '';
        }
    },
);
</script>

<template>
    <div class="position-relative" data-test="user-autocomplete">
        <div class="input-group">
            <input
                :id="id"
                type="text"
                class="form-control"
                :class="{'is-invalid': invalid}"
                :value="text"
                autocomplete="off"
                placeholder="Search users…"
                data-test="setting-input"
                @input="onInput"
                @focus="onFocus"
                @blur="open = false"
            />
            <button
                v-if="!required && (model !== null || text !== '')"
                type="button"
                class="btn"
                data-test="user-clear"
                @click="clear"
            >
                Clear
            </button>
        </div>
        <div v-if="open && options.length > 0" class="list-group position-absolute w-100 shadow" style="z-index: 10">
            <button
                v-for="option in options"
                :key="option.id"
                type="button"
                class="list-group-item list-group-item-action"
                data-test="user-option"
                @mousedown.prevent="choose(option)"
            >
                {{ option.nickname }}
            </button>
        </div>
    </div>
</template>

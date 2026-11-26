<script setup lang="ts">
import axios from 'axios';
import {computed, nextTick, onBeforeUnmount, ref} from 'vue';

interface UserOption {
    id: number;
    nickname: string;
}

type Status = 'idle' | 'loading' | 'error';

const SEARCH_DELAY_MS = 250;

const props = defineProps<{
    id: string;
    searchUrl: string;
    initialLabel?: string | null;
    invalid?: boolean;
    required?: boolean;
}>();

const model = defineModel<number | null>({default: null});

const selectedLabel = ref<string | null>(model.value !== null ? (props.initialLabel ?? `User #${model.value}`) : null);
const term = ref('');
const options = ref<UserOption[]>([]);
const status = ref<Status>('idle');
const open = ref(false);
const activeIndex = ref(-1);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const input = ref<HTMLInputElement | null>(null);

const listboxId = computed(() => `${props.id}-listbox`);
const optionId = (index: number): string => `${props.id}-option-${index}`;
const activeDescendant = computed(() => (activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined));
const showEmpty = computed(() => status.value === 'idle' && options.value.length === 0);

let timer: ReturnType<typeof setTimeout> | null = null;
let requestSeq = 0;

function cancelTimer(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
}

async function search(): Promise<void> {
    const seq = ++requestSeq;
    status.value = 'loading';
    try {
        const response = await axios.get<{data: UserOption[]}>(props.searchUrl, {params: {q: term.value}});
        if (seq !== requestSeq) {
            return;
        }
        options.value = response.data.data;
        activeIndex.value = options.value.length > 0 ? 0 : -1;
        status.value = 'idle';
    } catch {
        if (seq !== requestSeq) {
            return;
        }
        options.value = [];
        activeIndex.value = -1;
        status.value = 'error';
    }
}

async function openMenu(): Promise<void> {
    if (open.value) {
        return;
    }
    open.value = true;
    term.value = '';
    void search();
    await nextTick();
    input.value?.focus();
}

function closeMenu(refocus = false): void {
    cancelTimer();
    requestSeq++;
    open.value = false;
    status.value = 'idle';
    if (refocus) {
        void nextTick(() => trigger.value?.focus());
    }
}

function onInput(event: Event): void {
    term.value = (event.target as HTMLInputElement).value;
    cancelTimer();
    status.value = 'loading';
    timer = setTimeout(() => void search(), SEARCH_DELAY_MS);
}

function choose(option: UserOption): void {
    model.value = option.id;
    selectedLabel.value = option.nickname;
    closeMenu(true);
}

function clear(): void {
    model.value = null;
    selectedLabel.value = null;
    trigger.value?.focus();
}

function move(delta: number): void {
    const count = options.value.length;
    if (count === 0) {
        return;
    }
    activeIndex.value = (activeIndex.value + delta + count) % count;
    void nextTick(() => document.getElementById(optionId(activeIndex.value))?.scrollIntoView?.({block: 'nearest'}));
}

function onInputKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Enter':
            event.preventDefault();
            if (activeIndex.value >= 0 && options.value[activeIndex.value]) {
                choose(options.value[activeIndex.value]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            event.stopPropagation();
            closeMenu(true);
            break;
        case 'Tab':
            closeMenu();
            break;
    }
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        void openMenu();
    }
}

function onFocusout(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;
    if (open.value && (next === null || !root.value?.contains(next))) {
        closeMenu();
    }
}

onBeforeUnmount(cancelTimer);

function initial(name: string): string {
    return name.trim().charAt(0).toUpperCase();
}
</script>

<template>
    <div ref="root" class="user-picker position-relative" data-test="user-autocomplete" @focusout="onFocusout">
        <div class="d-flex gap-2">
            <button
                :id="id"
                ref="trigger"
                type="button"
                class="form-select text-start user-picker-trigger"
                :class="{'is-invalid': invalid}"
                aria-haspopup="listbox"
                :aria-expanded="open"
                :aria-controls="listboxId"
                data-test="user-trigger"
                @click="open ? closeMenu() : openMenu()"
                @keydown="onTriggerKeydown"
            >
                <span v-if="selectedLabel !== null" class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-xs" aria-hidden="true">{{ initial(selectedLabel) }}</span>
                    <span class="text-truncate" data-test="user-selected">{{ selectedLabel }}</span>
                </span>
                <span v-else class="text-secondary">Select a user</span>
            </button>
            <button
                v-if="!required && model !== null"
                type="button"
                class="btn btn-icon"
                aria-label="Clear selected user"
                data-test="user-clear"
                @click="clear"
            >
                <i class="icon ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        <div v-if="open" class="dropdown-menu show user-picker-menu w-100 p-0" data-test="user-menu">
            <div class="p-2 border-bottom">
                <input
                    ref="input"
                    type="text"
                    class="form-control"
                    role="combobox"
                    aria-label="Search users"
                    aria-autocomplete="list"
                    aria-expanded="true"
                    :aria-controls="listboxId"
                    :aria-activedescendant="activeDescendant"
                    :value="term"
                    autocomplete="off"
                    placeholder="Search users…"
                    data-test="user-search"
                    @input="onInput"
                    @keydown="onInputKeydown"
                />
            </div>
            <div :id="listboxId" role="listbox" aria-label="Users" class="user-picker-list py-1">
                <button
                    v-for="(option, index) in options"
                    :id="optionId(index)"
                    :key="option.id"
                    type="button"
                    role="option"
                    tabindex="-1"
                    class="dropdown-item d-flex align-items-center gap-2"
                    :class="{active: index === activeIndex}"
                    :aria-selected="option.id === model"
                    data-test="user-option"
                    @mousedown.prevent="choose(option)"
                    @mousemove="activeIndex = index"
                >
                    <span class="avatar avatar-xs" aria-hidden="true">{{ initial(option.nickname) }}</span>
                    <span class="text-truncate">{{ option.nickname }}</span>
                </button>
            </div>
            <div
                v-if="status === 'loading'"
                class="dropdown-item-text text-secondary"
                role="status"
                data-test="user-loading"
            >
                Searching…
            </div>
            <div
                v-else-if="status === 'error'"
                class="dropdown-item-text text-danger"
                role="alert"
                data-test="user-error"
            >
                Could not search users. Try again.
            </div>
            <div v-else-if="showEmpty" class="dropdown-item-text text-secondary" role="status" data-test="user-empty">
                No users found.
            </div>
        </div>
    </div>
</template>

<style scoped>
.user-picker-trigger {
    min-height: 2.25rem;
}

.user-picker-menu {
    top: 100%;
    z-index: 1050;
    margin-top: 0.25rem;
    background-color: var(--tblr-bg-surface);
    border: var(--tblr-border-width, 1px) solid var(--tblr-border-color);
    box-shadow: var(--tblr-box-shadow-dropdown, var(--tblr-box-shadow));
}

.user-picker-list {
    max-height: 15rem;
    overflow-y: auto;
}
</style>

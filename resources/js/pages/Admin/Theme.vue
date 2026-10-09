<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import {contrastWarnings, describeWarning} from '../../theme/contrast.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    theme: {type: Object, required: true},
    defaults: {type: Object, required: true},
    fonts: {type: Array, default: () => []},
    tokens: {type: Array, default: () => []},
    warnings: {type: Array, default: () => []},
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const schemes = [
    {code: 'light', label: 'Light'},
    {code: 'dark', label: 'Dark'},
];

const draft = reactive({
    light: {...props.theme.light},
    dark: {...props.theme.dark},
    font: props.theme.font,
});
const edited = ref(false);

const liveWarnings = computed(() => (edited.value ? contrastWarnings(draft) : props.warnings));

const setColour = (scheme, key, value) => {
    draft[scheme][key] = value;
    edited.value = true;
};

const nativeValue = (value) => (/^#[0-9a-f]{6}$/i.test(value ?? '') ? value : '#000000');

const fontStack = computed(() => {
    const font = props.fonts.find((f) => f.value === draft.font);
    return font ? `'${font.label}', sans-serif` : null;
});

const previewStyle = (scheme) => {
    const style = {};
    for (const token of props.tokens) {
        const value = draft[scheme][token.key];
        if (/^#[0-9a-f]{6}$/i.test(value ?? '')) {
            style[`--color-${token.key.replace(/_/g, '-')}`] = value;
        }
    }
    if (fontStack.value) {
        style['--font-sans'] = fontStack.value;
    }
    return style;
};

const save = () => {
    router.put('/admin/theme', {light: {...draft.light}, dark: {...draft.dark}, font: draft.font}, {preserveScroll: true});
};

const reset = () => {
    if (window.confirm('Reset the theme to defaults?')) {
        router.delete('/admin/theme', {preserveScroll: true});
    }
};
</script>

<template>
    <Head title="Theme" />
    <h1 class="text-xl font-semibold">Theme</h1>

    <div role="status" data-testid="warnings" class="mt-4 text-sm">
        <ul v-if="liveWarnings.length" class="rounded border border-danger bg-surface p-3">
            <li v-for="warning in liveWarnings" :key="`${warning.scheme}-${warning.pair}`" data-warning>
                {{ describeWarning(warning) }}
            </li>
        </ul>
    </div>

    <form class="mt-4 space-y-6" @submit.prevent="save">
        <div>
            <label for="theme-font" class="block text-sm">Font</label>
            <select
                id="theme-font"
                v-model="draft.font"
                class="mt-1 rounded border border-border bg-surface px-3 py-2 text-sm"
            >
                <option v-for="font in fonts" :key="font.value" :value="font.value">{{ font.label }}</option>
            </select>
            <p v-if="errors.font" role="alert" class="mt-1 text-sm text-danger">{{ errors.font }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <fieldset v-for="scheme in schemes" :key="scheme.code" :data-scheme="scheme.code" class="space-y-3">
                <legend class="font-semibold">{{ scheme.label }}</legend>

                <div v-for="token in tokens" :key="token.key" :data-token="token.key">
                    <div class="flex items-center gap-2">
                        <label :for="`${scheme.code}-${token.key}`" class="w-40 text-sm">{{ token.label }}</label>
                        <input
                            type="color"
                            :value="nativeValue(draft[scheme.code][token.key])"
                            :aria-label="`${scheme.label} ${token.label} picker`"
                            @input="setColour(scheme.code, token.key, $event.target.value)"
                        />
                        <input
                            :id="`${scheme.code}-${token.key}`"
                            type="text"
                            data-hex
                            :value="draft[scheme.code][token.key]"
                            :aria-invalid="errors[`${scheme.code}.${token.key}`] ? 'true' : undefined"
                            class="w-28 rounded border border-border bg-surface px-2 py-1 font-mono text-sm"
                            @input="setColour(scheme.code, token.key, $event.target.value)"
                        />
                    </div>
                    <p
                        v-if="errors[`${scheme.code}.${token.key}`]"
                        role="alert"
                        class="mt-1 text-sm text-danger"
                    >{{ errors[`${scheme.code}.${token.key}`] }}</p>
                </div>
            </fieldset>
        </div>

        <div class="flex gap-2">
            <button type="submit" data-action="save" class="rounded bg-primary px-3 py-2 text-sm text-white">Save</button>
            <button
                type="button"
                data-action="reset"
                class="rounded border border-border px-3 py-2 text-sm"
                @click="reset"
            >Reset to defaults</button>
        </div>
    </form>

    <h2 class="mt-8 text-lg font-semibold">Preview</h2>
    <div class="mt-3 grid gap-4 lg:grid-cols-2">
        <div
            v-for="scheme in schemes"
            :key="scheme.code"
            :data-preview="scheme.code"
            :style="previewStyle(scheme.code)"
            class="flex overflow-hidden rounded border border-border bg-background font-sans text-text"
        >
            <div class="w-24 bg-sidebar p-3 text-sm text-sidebar-text">
                <p class="text-sidebar-active">Sidebar</p>
                <p>Queue</p>
            </div>
            <div class="flex-1 p-3">
                <p class="text-sm">{{ scheme.label }} background</p>
                <div class="mt-2 rounded border border-border bg-surface p-3">
                    <p class="text-sm">Surface text</p>
                    <p class="text-sm text-muted">Muted text</p>
                    <button type="button" tabindex="-1" class="mt-2 rounded bg-primary px-3 py-1 text-sm text-white">
                        Primary
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

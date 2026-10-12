<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import SelectField from '../../Components/ui/SelectField.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';
import {contrastWarnings, describeWarning} from '../../theme/contrast.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    theme: {type: Object, required: true},
    defaults: {type: Object, required: true},
    fonts: {type: Array, default: () => []},
    tokens: {type: Array, default: () => []},
    warnings: {type: Array, default: () => []},
});

usePageMeta({
    title: 'Theme',
    crumbs: [
        {label: 'Home', href: '/'},
        {label: 'Admin', href: '/admin'},
        {label: 'Settings', href: '/admin/settings'},
        {label: 'Theme'},
    ],
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
    <div role="status" data-testid="warnings" class="mb-4 text-sm">
        <ul v-if="liveWarnings.length" class="rounded border border-danger bg-surface p-3">
            <li v-for="warning in liveWarnings" :key="`${warning.scheme}-${warning.pair}`" data-warning>
                {{ describeWarning(warning) }}
            </li>
        </ul>
    </div>

    <form @submit.prevent="save">
        <Card>
            <template #header>Colours and font</template>
            <div class="space-y-6">
        <div>
            <label for="theme-font" class="block text-sm font-medium">Font</label>
            <SelectField id="theme-font" v-model="draft.font" :options="fonts" class="mt-1" />
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
                            class="w-28 rounded border border-border bg-background px-2 py-1 font-mono text-sm"
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

            </div>
            <template #footer>
                <Button variant="secondary" data-action="reset" @click="reset">Reset to defaults</Button>
                <Button type="submit" data-action="save">Save</Button>
            </template>
        </Card>
    </form>

    <Card class="mt-4">
    <template #header>Preview</template>
    <div class="grid gap-4 lg:grid-cols-2">
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
</Card>
</template>

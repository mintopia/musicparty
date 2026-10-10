<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import {contrastWarnings, describeWarning} from '../../theme/contrast.js';

const props = defineProps({
    party: {type: Object, required: true},
    theme: {type: Object, required: true},
    fonts: {type: Array, default: () => []},
    tokens: {type: Array, default: () => []},
    layouts: {type: Array, default: () => []},
    warnings: {type: Array, default: () => []},
});

usePartyPresence(props.party.code);

const schemes = [
    {code: 'light', label: 'Light'},
    {code: 'dark', label: 'Dark'},
];

const form = useForm({
    light: {...props.theme.overrides.light},
    dark: {...props.theme.overrides.dark},
    font: props.theme.overrides.font ?? '',
    tv_layout: props.theme.tv_layout,
    logo: null,
    background: null,
    remove_logo: false,
    remove_background: false,
});
const edited = ref(false);

const liveWarnings = computed(() => {
    if (!edited.value) {
        return props.warnings;
    }
    const merged = {};
    for (const {code} of schemes) {
        merged[code] = {...props.theme[code], ...form[code]};
    }
    return contrastWarnings(merged);
});

const isHex = (value) => /^#[0-9a-f]{6}$/i.test(value ?? '');
const shownColour = (scheme, key) => form[scheme][key] ?? props.theme[scheme][key] ?? '';

const setColour = (scheme, key, value) => {
    form[scheme][key] = value;
    edited.value = true;
};

const clearColour = (scheme, key) => {
    delete form[scheme][key];
    edited.value = true;
};

const setFile = (field, event) => {
    form[field] = event.target.files?.[0] ?? null;
    form[`remove_${field}`] = false;
};

const save = () => {
    form.transform((data) => {
        const payload = {...data, font: data.font === '' ? null : data.font};
        for (const field of ['logo', 'background']) {
            if (payload[field] === null) {
                delete payload[field];
            }
        }
        for (const scheme of ['light', 'dark']) {
            payload[scheme] = Object.fromEntries(Object.entries(payload[scheme]).map(([k, v]) => [k, v === '' ? null : v]));
        }
        return payload;
    }).post(`/parties/${props.party.code}/theme`, {forceFormData: true, preserveScroll: true});
};

const reset = () => {
    if (window.confirm('Reset the party theme to the instance theme?')) {
        router.delete(`/parties/${props.party.code}/theme`, {preserveScroll: true});
    }
};
</script>

<template>
    <Head :title="`${party.name} theme`" />
    <h1 class="text-xl font-semibold">Party theme</h1>

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
            <select id="theme-font" v-model="form.font" class="mt-1 rounded border border-border bg-surface px-3 py-2 text-sm">
                <option value="">Inherit from instance</option>
                <option v-for="font in fonts" :key="font.value" :value="font.value">{{ font.label }}</option>
            </select>
            <p v-if="form.errors.font" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.font }}</p>
        </div>

        <div>
            <label for="theme-layout" class="block text-sm">TV layout</label>
            <select id="theme-layout" v-model="form.tv_layout" class="mt-1 rounded border border-border bg-surface px-3 py-2 text-sm">
                <option v-for="layout in layouts" :key="layout.value" :value="layout.value">{{ layout.label }}</option>
            </select>
            <p v-if="form.errors.tv_layout" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.tv_layout }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <fieldset v-for="scheme in schemes" :key="scheme.code" :data-scheme="scheme.code" class="space-y-3">
                <legend class="font-semibold">{{ scheme.label }}</legend>
                <div v-for="token in tokens" :key="token.key" :data-token="token.key">
                    <div class="flex items-center gap-2">
                        <label :for="`${scheme.code}-${token.key}`" class="w-32 text-sm">{{ token.label }}</label>
                        <input
                            type="color"
                            :value="isHex(shownColour(scheme.code, token.key)) ? shownColour(scheme.code, token.key) : '#000000'"
                            :aria-label="`${scheme.label} ${token.label} picker`"
                            @input="setColour(scheme.code, token.key, $event.target.value)"
                        />
                        <input
                            :id="`${scheme.code}-${token.key}`"
                            type="text"
                            data-hex
                            :value="shownColour(scheme.code, token.key)"
                            :placeholder="theme[scheme.code][token.key]"
                            :aria-invalid="form.errors[`${scheme.code}.${token.key}`] ? 'true' : undefined"
                            class="w-28 rounded border border-border bg-surface px-2 py-1 font-mono text-sm"
                            @input="setColour(scheme.code, token.key, $event.target.value)"
                        />
                        <button
                            type="button"
                            data-action="clear"
                            class="rounded border border-border px-2 py-1 text-xs"
                            :disabled="form[scheme.code][token.key] === undefined"
                            @click="clearColour(scheme.code, token.key)"
                        >Inherit</button>
                    </div>
                    <p v-if="form.errors[`${scheme.code}.${token.key}`]" role="alert" class="mt-1 text-sm text-danger">{{ form.errors[`${scheme.code}.${token.key}`] }}</p>
                </div>
            </fieldset>
        </div>

        <div v-for="field in ['logo', 'background']" :key="field" :data-upload="field">
            <label :for="`theme-${field}`" class="block text-sm">{{ field === 'logo' ? 'Logo' : 'Background' }}</label>
            <input :id="`theme-${field}`" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 text-sm" @change="setFile(field, $event)" />
            <label class="mt-1 flex items-center gap-2 text-sm">
                <input v-model="form[`remove_${field}`]" type="checkbox" :data-remove="field" />
                Remove current {{ field }}
            </label>
            <p v-if="form.errors[field]" role="alert" class="mt-1 text-sm text-danger">{{ form.errors[field] }}</p>
        </div>

        <div class="flex gap-2">
            <button type="submit" data-action="save" :disabled="form.processing" class="rounded bg-primary px-3 py-2 text-sm text-white">Save</button>
            <button type="button" data-action="reset" class="rounded border border-border px-3 py-2 text-sm" @click="reset">Reset to instance theme</button>
        </div>
    </form>
</template>

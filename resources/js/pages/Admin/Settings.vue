<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

const props = defineProps({
    settings: {type: Object, required: true},
});

const form = useForm({
    name: props.settings.name ?? '',
    terms_url: props.settings.terms_url ?? '',
    privacy_url: props.settings.privacy_url ?? '',
    default_party: props.settings.default_party ?? '',
    logo_light: null,
    logo_dark: null,
    favicon: null,
});

const submit = () => form.post('/admin/settings', {forceFormData: true, preserveScroll: true});
</script>

<template>
    <Head title="Site settings" />
    <h1 class="text-xl font-semibold">Site settings</h1>

    <form class="mt-4 flex max-w-lg flex-col gap-4" @submit.prevent="submit">
        <div v-for="field in [
            {id: 'name', label: 'Site name'},
            {id: 'terms_url', label: 'Terms of service URL'},
            {id: 'privacy_url', label: 'Privacy policy URL'},
            {id: 'default_party', label: 'Default party code'},
        ]" :key="field.id">
            <label :for="field.id" class="block text-sm">{{ field.label }}</label>
            <input
                :id="field.id"
                v-model="form[field.id]"
                type="text"
                class="mt-1 w-full rounded border border-border bg-surface px-3 py-2 text-sm"
            />
            <p v-if="form.errors[field.id]" role="alert" class="mt-1 text-sm">{{ form.errors[field.id] }}</p>
        </div>

        <div v-for="field in [
            {id: 'logo_light', label: 'Logo (for dark backgrounds)', url: settings.logo_light_url},
            {id: 'logo_dark', label: 'Logo (for light backgrounds)', url: settings.logo_dark_url},
            {id: 'favicon', label: 'Favicon', url: settings.favicon_url},
        ]" :key="field.id">
            <label :for="field.id" class="block text-sm">{{ field.label }}</label>
            <img v-if="field.url" :src="field.url" :alt="`Current ${field.label}`" class="mt-1 h-10" />
            <input
                :id="field.id"
                type="file"
                accept="image/*"
                class="mt-1 block text-sm"
                @change="form[field.id] = $event.target.files[0] ?? null"
            />
            <p v-if="form.errors[field.id]" role="alert" class="mt-1 text-sm">{{ form.errors[field.id] }}</p>
        </div>

        <div>
            <button type="submit" :disabled="form.processing" class="rounded border border-border px-3 py-1 text-sm">Save settings</button>
        </div>
    </form>
</template>

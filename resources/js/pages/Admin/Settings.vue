<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Button from '../../Components/ui/Button.vue';
import ButtonLink from '../../Components/ui/ButtonLink.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import StatusPill from '../../Components/ui/StatusPill.vue';
import Icon from '../../Components/Icon.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    settings: {type: Object, required: true},
    providers: {type: Array, default: () => []},
    theme: {type: Object, default: () => ({light: {}, dark: {}, font: ''})},
});

usePageMeta({
    title: 'Settings',
    crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Settings'}],
});

const inputClass = 'w-full rounded border border-border bg-background px-3 py-2 text-sm text-text';

const form = useForm({
    name: props.settings.name ?? '',
    terms_url: props.settings.terms_url ?? '',
    privacy_url: props.settings.privacy_url ?? '',
    default_party: props.settings.default_party ?? '',
    logo_light: null,
    logo_dark: null,
    cover_image: null,
    favicon: null,
});

const textFields = [
    {id: 'name', label: 'Site Name', required: true},
    {id: 'terms_url', label: 'Terms and Conditions URL'},
    {id: 'privacy_url', label: 'Privacy Policy URL'},
];

const fileFields = [
    {id: 'logo_light', label: 'Site Logo (Light Version)', url: props.settings.logo_light_url, hint: 'A light version of the logo for use on dark backgrounds'},
    {id: 'logo_dark', label: 'Site Logo (Dark Version)', url: props.settings.logo_dark_url, hint: 'A dark version of the logo for use on light backgrounds'},
    {id: 'cover_image', label: 'Login Cover Image', url: props.settings.cover_image_url, hint: 'The large image shown during login and signup'},
    {id: 'favicon', label: 'Favicon', url: props.settings.favicon_url, hint: 'An image to use as the favicon'},
];

const swatchKeys = ['primary', 'accent', 'background', 'surface', 'text', 'danger'];
const schemes = [
    {code: 'light', label: 'Light'},
    {code: 'dark', label: 'Dark'},
];

const submit = () => form.post('/admin/settings', {forceFormData: true, preserveScroll: true});
const yesNo = (value) => (value ? 'Yes' : 'No');
</script>

<template>
    <Head title="Settings" />

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <form @submit.prevent="submit">
            <Card>
                <FormField v-for="field in textFields" :key="field.id" :label="field.label" :for="field.id" :required="field.required" :error="form.errors[field.id]">
                    <input :id="field.id" v-model="form[field.id]" type="text" :class="inputClass" />
                </FormField>

                <FormField v-for="field in fileFields" :key="field.id" :label="field.label" :for="field.id" :hint="field.hint" :error="form.errors[field.id]">
                    <img v-if="field.url" :src="field.url" :alt="`Current ${field.label}`" class="mb-2 h-10 rounded" />
                    <input
                        :id="field.id"
                        type="file"
                        accept="image/*"
                        class="block w-full rounded border border-border bg-background px-3 py-2 text-sm text-muted"
                        @change="form[field.id] = $event.target.files[0] ?? null"
                    />
                </FormField>

                <FormField label="Default Party Code" for="default_party" hint="If specified, users will be automatically taken to this party" :error="form.errors.default_party">
                    <input id="default_party" v-model="form.default_party" type="text" :class="inputClass" />
                </FormField>

                <template #footer>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </template>
            </Card>
        </form>

        <Card data-testid="theme-card">
            <template #header>Theme</template>
            <template #actions>
                <ButtonLink href="/admin/theme">
                    <template #icon><Icon name="edit" /></template>
                    Edit theme
                </ButtonLink>
            </template>
            <dl class="flex flex-col gap-4 text-sm">
                <div v-for="scheme in schemes" :key="scheme.code" class="flex items-center justify-between gap-4">
                    <dt class="text-muted">{{ scheme.label }}</dt>
                    <dd class="flex gap-1.5">
                        <span
                            v-for="key in swatchKeys"
                            :key="key"
                            :data-swatch="`${scheme.code}-${key}`"
                            :title="key"
                            class="h-6 w-6 rounded border border-border"
                            :style="{backgroundColor: theme[scheme.code]?.[key]}"
                        />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted">Font</dt>
                    <dd data-testid="theme-font">{{ theme.font }}</dd>
                </div>
            </dl>
        </Card>
    </div>

    <Card class="mt-6" flush data-testid="providers-card">
        <template #header>Social Providers</template>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-background text-xs uppercase tracking-wide text-muted">
                        <th scope="col" class="px-5 py-3 font-semibold">Name</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Supports Auth</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Enabled</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Auth Enabled</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Return URLs</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="provider in providers" :key="provider.code" :data-testid="`provider-${provider.code}`" class="border-t border-border">
                        <td class="px-5 py-3">
                            <div>{{ provider.name }}</div>
                            <div class="text-muted">{{ provider.code }}</div>
                        </td>
                        <td class="px-5 py-3"><StatusPill :variant="provider.supports_auth ? 'green' : 'muted'">{{ yesNo(provider.supports_auth) }}</StatusPill></td>
                        <td class="px-5 py-3"><StatusPill :variant="provider.enabled ? 'green' : 'red'">{{ yesNo(provider.enabled) }}</StatusPill></td>
                        <td class="px-5 py-3">
                            <StatusPill v-if="provider.supports_auth" :variant="provider.auth_enabled ? 'green' : 'red'">{{ yesNo(provider.auth_enabled) }}</StatusPill>
                            <StatusPill v-else variant="muted">N/A</StatusPill>
                        </td>
                        <td class="px-5 py-3">
                            <div v-for="url in provider.return_urls" :key="url">{{ url }}</div>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <ButtonLink :href="`/admin/providers/${provider.code}/edit`" variant="outline-primary">
                                <template #icon><Icon name="edit" /></template>
                                Edit
                            </ButtonLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>

<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Button from '../../../Components/ui/Button.vue';
import ButtonLink from '../../../Components/ui/ButtonLink.vue';
import Card from '../../../Components/ui/Card.vue';
import FormField from '../../../Components/ui/FormField.vue';
import Toggle from '../../../Components/ui/Toggle.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    provider: {type: Object, required: true},
});

usePageMeta({
    title: `Edit ${props.provider.name}`,
    crumbs: [
        {label: 'Home', href: '/'},
        {label: 'Admin', href: '/admin'},
        {label: 'Settings', href: '/admin/settings'},
        {label: `Edit ${props.provider.name}`},
    ],
});

const form = useForm({
    settings: Object.fromEntries(props.provider.fields.map((field) => [field.code, field.value ?? ''])),
    enabled: props.provider.enabled,
    auth_enabled: props.provider.auth_enabled,
});

const submit = () => {
    form.transform((data) => (props.provider.supports_auth ? data : {settings: data.settings, enabled: data.enabled}))
        .put(`/admin/providers/${props.provider.code}`, {preserveScroll: true});
};

const setEnabled = (value) => {
    form.enabled = value;
    if (!value) {
        form.auth_enabled = false;
    }
};
</script>

<template>
    <Head :title="`Edit ${provider.name}`" />

    <form class="mx-auto max-w-3xl" @submit.prevent="submit">
        <Card>
            <FormField
                v-for="field in provider.fields"
                :key="field.code"
                :label="field.name"
                :for="`field-${field.code}`"
                required
                :error="form.errors[`settings.${field.code}`]"
            >
                <input
                    :id="`field-${field.code}`"
                    v-model="form.settings[field.code]"
                    :type="field.secret ? 'password' : 'text'"
                    autocomplete="off"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm text-text"
                />
            </FormField>

            <div class="flex flex-col gap-3">
                <Toggle :model-value="form.enabled" label="Enabled" @update:model-value="setEnabled" />
                <p v-if="form.errors.enabled" role="alert" class="text-sm text-danger">{{ form.errors.enabled }}</p>
                <Toggle
                    v-if="provider.supports_auth"
                    v-model="form.auth_enabled"
                    label="Authentication Enabled"
                    :disabled="!form.enabled"
                />
                <p v-if="form.errors.auth_enabled" role="alert" class="text-sm text-danger">{{ form.errors.auth_enabled }}</p>
            </div>

            <template #footer>
                <ButtonLink href="/admin/settings" variant="link" class="mr-auto">Cancel</ButtonLink>
                <Button type="submit" :disabled="form.processing">Save</Button>
            </template>
        </Card>
    </form>
</template>

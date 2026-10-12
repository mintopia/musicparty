<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Button from '../../Components/ui/Button.vue';
import ButtonLink from '../../Components/ui/ButtonLink.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({nickname: {type: String, required: true}});

usePageMeta(() => ({
    title: 'Edit Profile',
    crumbs: [{label: 'Home', href: '/'}, {label: 'Profile', href: '/profile'}, {label: 'Edit Profile'}],
}));

const form = useForm({nickname: props.nickname});

const submit = () => form.patch('/profile');
</script>

<template>
    <Head title="Edit Profile" />
    <form class="mx-auto md:w-1/2" @submit.prevent="submit">
        <Card>
            <FormField label="Nickname" for="nickname" required :error="form.errors.nickname">
                <input
                    id="nickname"
                    v-model="form.nickname"
                    type="text"
                    required
                    maxlength="32"
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                />
                <p class="mt-1 text-xs text-muted">Your preferred nickname. 1–32 characters. Must be unique, ignoring case.</p>
            </FormField>
            <template #footer>
                <ButtonLink href="/profile" variant="link" data-action="cancel">Cancel</ButtonLink>
                <Button type="submit" :disabled="form.processing" data-action="save">Save</Button>
            </template>
        </Card>
    </form>
</template>

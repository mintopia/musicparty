<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../Components/ui/Button.vue';
import ButtonLink from '../../Components/ui/ButtonLink.vue';
import Card from '../../Components/ui/Card.vue';
import DataGrid from '../../Components/ui/DataGrid.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({account: {type: Object, required: true}});

usePageMeta(() => ({
    title: 'Remove linked account',
    crumbs: [{label: 'Home', href: '/'}, {label: 'Profile', href: '/profile'}, {label: 'Remove linked account'}],
}));

const items = computed(() => [
    {label: 'Provider', value: props.account.provider},
    {label: 'Account', value: props.account.name},
    {label: 'Email', value: props.account.email},
]);

const form = useForm({});
const submit = () => form.delete(`/profile/accounts/${props.account.id}`);
</script>

<template>
    <Head title="Remove linked account" />
    <form @submit.prevent="submit">
        <Card status="danger">
            <template #header>Remove linked account</template>
            <p class="mb-4 text-sm">You will no longer be able to use this account. This cannot be undone.</p>
            <DataGrid :items="items" />
            <template #footer>
                <ButtonLink href="/profile" variant="secondary" data-action="cancel">Cancel</ButtonLink>
                <Button type="submit" variant="danger" :disabled="form.processing" data-action="confirm-delete">
                    <template #icon><Icon name="trash" /></template>
                    Remove linked account
                </Button>
            </template>
        </Card>
    </form>
</template>

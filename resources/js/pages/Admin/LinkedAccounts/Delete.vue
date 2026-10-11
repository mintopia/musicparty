<script setup>
import {Head, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import Alert from '../../../Components/ui/Alert.vue';
import Button from '../../../Components/ui/Button.vue';
import ButtonLink from '../../../Components/ui/ButtonLink.vue';
import Card from '../../../Components/ui/Card.vue';
import DataGrid from '../../../Components/ui/DataGrid.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    user: {type: Object, required: true},
    account: {type: Object, required: true},
});

const page = usePage();
const error = computed(() => page.props.errors?.admin ?? null);

usePageMeta(() => ({
    title: 'Delete linked account',
    crumbs: [
        {label: 'Home', href: '/'},
        {label: 'Admin', href: '/admin'},
        {label: 'Users', href: '/admin/users'},
        {label: props.user.nickname, href: `/admin/users/${props.user.id}`},
        {label: 'Delete linked account'},
    ],
}));

const items = computed(() => [
    {label: 'Provider', value: props.account.provider},
    {label: 'Nickname', value: props.account.name},
    {label: 'Email', value: props.account.email},
    {label: 'External ID', value: props.account.external_id},
]);

const form = useForm({});
const submit = () => form.delete(`/admin/users/${props.user.id}/accounts/${props.account.id}`);
</script>

<template>
    <Head title="Delete linked account" />
    <div v-if="error" class="mb-4"><Alert variant="danger">{{ error }}</Alert></div>
    <form @submit.prevent="submit">
        <Card status="danger">
            <template #header>Delete linked account for {{ user.nickname }}</template>
            <p class="mb-4 text-sm">The user will no longer be able to use this account. This cannot be undone.</p>
            <DataGrid :items="items" />
            <template #footer>
                <ButtonLink :href="`/admin/users/${user.id}`" variant="secondary" data-action="cancel">Cancel</ButtonLink>
                <Button type="submit" variant="danger" :disabled="form.processing" data-action="confirm-delete">
                    <template #icon><Icon name="trash" /></template>
                    Delete linked account
                </Button>
            </template>
        </Card>
    </form>
</template>

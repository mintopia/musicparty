<script setup>
import {Head} from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../Components/ui/Button.vue';
import ButtonLink from '../../Components/ui/ButtonLink.vue';
import Card from '../../Components/ui/Card.vue';
import DataGrid from '../../Components/ui/DataGrid.vue';
import DataTable from '../../Components/ui/DataTable.vue';
import {usePageMeta} from '../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    profile: {type: Object, required: true},
    accounts: {type: Array, required: true},
    available_links: {type: Array, default: () => []},
});

usePageMeta(() => ({
    title: 'Profile',
    crumbs: [{label: 'Home', href: '/'}, {label: 'Profile'}],
}));

const items = [
    {label: 'Nickname', value: props.profile.nickname},
    {label: 'Email', value: props.profile.email},
];

const columns = [
    {key: 'provider', label: 'Provider'},
    {key: 'name', label: 'Account'},
    {key: 'actions', label: '', class: 'text-right'},
];
</script>

<template>
    <Head title="Profile" />
    <Card>
        <div class="flex items-center gap-5">
            <img :src="profile.avatar_url" alt="" class="h-16 w-16 rounded object-cover" data-testid="profile-avatar" />
            <DataGrid :items="items" class="flex-1" />
        </div>
        <template #footer>
            <ButtonLink href="/profile/edit" data-action="edit">
                <template #icon><Icon name="edit" /></template>
                Edit
            </ButtonLink>
        </template>
    </Card>

    <h2 class="mb-4 mt-8 text-2xl font-semibold">Linked Accounts</h2>
    <Card flush>
        <DataTable
            :columns="columns"
            :rows="accounts"
            empty-text="No linked accounts."
            :row-attrs="(account) => ({'data-testid': `account-${account.id}`})"
        >
            <template #row="{row: account}">
                <td class="px-5 py-3">{{ account.provider }}</td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <img v-if="account.avatar_url" :src="account.avatar_url" alt="" class="h-9 w-9 rounded object-cover" />
                        <span v-else class="h-9 w-9 rounded bg-background" aria-hidden="true" />
                        <span>{{ account.name }}</span>
                    </div>
                </td>
                <td class="px-5 py-3 text-right">
                    <ButtonLink
                        v-if="account.can_delete"
                        :href="`/profile/accounts/${account.id}/delete`"
                        variant="outline-danger"
                        data-action="remove-account"
                    >
                        <template #icon><Icon name="trash" /></template>
                        Remove
                    </ButtonLink>
                    <Button v-else variant="outline-danger" disabled data-action="remove-account">
                        <template #icon><Icon name="trash" /></template>
                        Remove
                    </Button>
                </td>
            </template>
        </DataTable>
        <div v-if="available_links.length" class="flex flex-wrap gap-2 border-t border-border bg-background px-5 py-3">
            <a
                v-for="link in available_links"
                :key="link.code"
                :href="link.url"
                class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:brightness-110"
                :data-link="link.code"
            >
                <Icon name="plus" />
                Link with {{ link.name }}
            </a>
        </div>
    </Card>
</template>

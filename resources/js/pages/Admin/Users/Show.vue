<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import Alert from '../../../Components/ui/Alert.vue';
import Badge from '../../../Components/ui/Badge.vue';
import Button from '../../../Components/ui/Button.vue';
import ButtonLink from '../../../Components/ui/ButtonLink.vue';
import Card from '../../../Components/ui/Card.vue';
import DataGrid from '../../../Components/ui/DataGrid.vue';
import DataTable from '../../../Components/ui/DataTable.vue';
import RelativeTime from '../../../Components/ui/RelativeTime.vue';
import StatusPill from '../../../Components/ui/StatusPill.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({user: {type: Object, required: true}});

const page = usePage();
const error = computed(() => page.props.errors?.admin ?? null);

usePageMeta(() => ({
    title: props.user.nickname,
    crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Users', href: '/admin/users'}, {label: props.user.nickname}],
}));

const items = computed(() => [
    {label: 'ID', value: props.user.id},
    {label: 'Nickname', value: props.user.nickname},
    {label: 'Email', value: props.user.email},
    {label: 'Roles', value: props.user.roles},
    {label: 'Completed signup?', value: props.user.completed_signup, pill: true},
    {label: 'Agreed terms and conditions', value: props.user.agreed_terms, pill: true},
    {label: 'Suspended', value: props.user.suspended, pill: true, invert: true},
    {label: 'Last login', value: props.user.last_login, time: true},
    {label: 'Created', value: props.user.created_at, time: true},
]);

const isAdmin = computed(() => props.user.roles.some((role) => role.code === 'admin'));
const canImpersonate = computed(() => !props.user.suspended && !isAdmin.value && page.props.auth?.user?.id !== props.user.id);
const impersonate = () => router.post(`/admin/users/${props.user.id}/impersonate`);

const columns = [
    {key: 'id', label: 'ID'},
    {key: 'provider', label: 'Provider'},
    {key: 'nickname', label: 'Nickname'},
    {key: 'external_id', label: 'External ID'},
    {key: 'actions', label: '', class: 'text-right'},
];
</script>

<template>
    <Head :title="user.nickname" />
    <div v-if="error" class="mb-4"><Alert variant="danger">{{ error }}</Alert></div>
    <div v-if="user.suspended" class="mb-4"><Alert variant="warning">This user is suspended.</Alert></div>

    <Card>
        <DataGrid :items="items">
            <template #value="{item}">
                <template v-if="item.label === 'Roles'">
                    <span class="flex flex-wrap gap-1">
                        <Badge v-for="role in item.value" :key="role.code">{{ role.label }}</Badge>
                    </span>
                </template>
                <a v-else-if="item.label === 'Email' && item.value" :href="`mailto:${item.value}`" class="text-primary hover:underline">{{ item.value }}</a>
                <StatusPill v-else-if="item.pill" :variant="item.value === !!item.invert ? 'red' : 'green'" :data-pill="item.label">{{ item.value ? 'Yes' : 'No' }}</StatusPill>
                <RelativeTime v-else-if="item.time" :value="item.value" />
                <template v-else>{{ item.value }}</template>
            </template>
        </DataGrid>
        <template #footer>
            <div class="flex w-full flex-wrap items-center justify-between gap-2">
                <ButtonLink :href="`/admin/users/${user.id}/delete`" variant="outline-danger" data-action="delete">
                    <template #icon><Icon name="trash" /></template>
                    Delete
                </ButtonLink>
                <div class="flex items-center gap-2">
                    <Button variant="secondary" :disabled="!canImpersonate" data-action="impersonate" @click="impersonate">
                        <template #icon><Icon name="spy" /></template>
                        Impersonate
                    </Button>
                    <ButtonLink :href="`/admin/users/${user.id}/edit`" data-action="edit">
                        <template #icon><Icon name="edit" /></template>
                        Edit
                    </ButtonLink>
                </div>
            </div>
        </template>
    </Card>

    <h2 class="mb-4 mt-8 text-2xl font-semibold">Linked Accounts</h2>
    <Card flush>
        <DataTable
            :columns="columns"
            :rows="user.accounts"
            empty-text="No linked accounts."
            :row-attrs="(account) => ({'data-testid': `account-${account.id}`})"
        >
            <template #row="{row: account}">
                <td class="px-5 py-3 text-muted">{{ account.id }}</td>
                <td class="px-5 py-3">{{ account.provider }}</td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <img v-if="account.avatar_url" :src="account.avatar_url" alt="" class="h-9 w-9 rounded object-cover" />
                        <span v-else class="h-9 w-9 rounded bg-background" aria-hidden="true" />
                        <div>
                            <div>{{ account.name }}</div>
                            <div v-if="account.email" class="text-muted">{{ account.email }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3">{{ account.external_id }}</td>
                <td class="px-5 py-3 text-right">
                    <ButtonLink
                        v-if="account.can_delete"
                        :href="`/admin/users/${user.id}/accounts/${account.id}/delete`"
                        variant="outline-danger"
                        data-action="delete-account"
                    >
                        <template #icon><Icon name="trash" /></template>
                        Delete
                    </ButtonLink>
                    <Button v-else variant="outline-danger" disabled data-action="delete-account">
                        <template #icon><Icon name="trash" /></template>
                        Delete
                    </Button>
                </td>
            </template>
        </DataTable>
    </Card>
</template>

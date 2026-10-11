<script setup>
import {Head, Link} from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/ui/Card.vue';
import DataTable from '../../../Components/ui/DataTable.vue';
import Pagination from '../../../Components/ui/Pagination.vue';
import RelativeTime from '../../../Components/ui/RelativeTime.vue';
import SearchCard from '../../../Components/ui/SearchCard.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

defineProps({
    users: {type: Object, required: true},
    filters: {type: Object, default: () => ({})},
});

usePageMeta({title: 'Users', crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Users'}]});

const fields = [
    {name: 'id', label: 'ID'},
    {name: 'nickname', label: 'Nickname'},
];

const columns = [
    {key: 'id', label: 'ID', sortable: true},
    {key: 'nickname', label: 'Nickname', sortable: true},
    {key: 'last_login', label: 'Last login', sortable: true},
    {key: 'created_at', label: 'Created', sortable: true},
];
</script>

<template>
    <Head title="Users" />
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(16rem,22rem)_1fr]">
        <SearchCard :fields="fields" :filters="filters" />
        <Card flush>
            <DataTable
                :columns="columns"
                :rows="users.data"
                default-order="id"
                caption="Users"
                empty-text="No users found."
                :row-attrs="(user) => ({'data-testid': `user-${user.id}`})"
            >
                <template #row="{row: user}">
                    <td class="px-5 py-3 text-muted">{{ user.id }}</td>
                    <td class="px-5 py-3">
                        <Link :href="`/admin/users/${user.id}`" class="text-primary hover:underline">{{ user.nickname }}</Link>
                    </td>
                    <td class="px-5 py-3"><RelativeTime :value="user.last_login" /></td>
                    <td class="px-5 py-3"><RelativeTime :value="user.created_at" /></td>
                </template>
            </DataTable>
            <Pagination :paginator="users" />
        </Card>
    </div>
</template>

<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

const props = defineProps({
    users: {type: Object, required: true},
    filters: {type: Object, default: () => ({})},
});

const roles = [
    {code: 'admin', label: 'Admin'},
    {code: 'create-party', label: 'Create party'},
];

const page = usePage();
const error = computed(() => page.props.errors?.admin ?? null);
const search = ref(props.filters.search ?? '');

let timer = null;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/users', {search: value}, {preserveState: true, replace: true});
    }, 300);
});

const hasRole = (user, code) => user.roles.includes(code);
const toggleSuspend = (user) => {
    const url = `/admin/users/${user.id}/suspend`;
    if (user.suspended) {
        router.delete(url, {preserveScroll: true});
    } else {
        router.post(url, {}, {preserveScroll: true});
    }
};
const toggleRole = (user, code) => {
    if (hasRole(user, code)) {
        router.delete(`/admin/users/${user.id}/roles/${code}`, {preserveScroll: true});
    } else {
        router.post(`/admin/users/${user.id}/roles`, {role: code}, {preserveScroll: true});
    }
};
</script>

<template>
    <Head title="Users" />
    <h1 class="text-xl font-semibold">Users</h1>

    <div v-if="error" role="alert" class="mt-4 rounded border border-border bg-surface p-3 text-sm">
        {{ error }}
    </div>

    <div class="mt-4">
        <label for="user-search" class="block text-sm">Search users</label>
        <input
            id="user-search"
            v-model="search"
            type="search"
            class="mt-1 w-full max-w-sm rounded border border-border bg-surface px-3 py-2 text-sm"
        />
    </div>

    <table class="mt-4 w-full text-left text-sm">
        <caption class="sr-only">Users</caption>
        <thead>
            <tr>
                <th scope="col" class="py-2">Nickname</th>
                <th scope="col" class="py-2">Status</th>
                <th scope="col" class="py-2">Roles</th>
                <th scope="col" class="py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="user in users.data" :key="user.id" :data-testid="`user-${user.id}`" class="border-t border-border">
                <td class="py-2">{{ user.nickname }}</td>
                <td class="py-2">{{ user.suspended ? 'Suspended' : 'Active' }}</td>
                <td class="py-2">
                    <label v-for="role in roles" :key="role.code" class="mr-3 inline-flex items-center gap-1">
                        <input
                            type="checkbox"
                            :data-role="role.code"
                            :checked="hasRole(user, role.code)"
                            :aria-label="`${role.label} role for ${user.nickname}`"
                            @change="toggleRole(user, role.code)"
                        />
                        {{ role.label }}
                    </label>
                </td>
                <td class="py-2">
                    <button
                        type="button"
                        data-action="suspend"
                        class="rounded border border-border px-2 py-1"
                        @click="toggleSuspend(user)"
                    >{{ user.suspended ? 'Unsuspend' : 'Suspend' }}</button>
                </td>
            </tr>
            <tr v-if="!users.data.length">
                <td colspan="4" class="py-4 text-muted">No users found.</td>
            </tr>
        </tbody>
    </table>

    <nav v-if="users.last_page > 1" aria-label="Pagination" class="mt-4 flex flex-wrap gap-1">
        <template v-for="(link, index) in users.links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded border border-border px-2 py-1 text-sm"
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
            <span v-else class="px-2 py-1 text-sm text-muted" v-html="link.label" />
        </template>
    </nav>
</template>

<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import Pagination from '@/components/Pagination.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import SortHeader from '@/components/SortHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {Paginator} from '@/types';

interface AdminUserRow {
    id: number;
    nickname: string;
    is_admin?: boolean;
    suspended?: boolean;
    last_login: string | null;
    last_login_human: string | null;
    created_at: string;
    created_at_human: string;
}

const props = defineProps<{
    users: Paginator<AdminUserRow> & {total?: number};
    filters: {q: string; role: string; suspended: boolean; first_login: boolean; provider: string};
    providers: {code: string; name: string}[];
    total: number;
    order: string;
    order_direction: 'asc' | 'desc';
}>();

const form = reactive({...props.filters});

const filtered = computed(
    () =>
        props.filters.q !== '' ||
        props.filters.role !== '' ||
        props.filters.suspended ||
        props.filters.first_login ||
        props.filters.provider !== '',
);

const chips = computed<SearchChip[]>(() => [
    {key: 'role', label: 'Admin', pressed: form.role === 'admin'},
    {key: 'suspended', label: 'Suspended', pressed: form.suspended},
    {key: 'first_login', label: 'Signup not finished', pressed: form.first_login},
    ...props.providers.map((provider) => ({
        key: `provider:${provider.code}`,
        label: provider.name,
        pressed: form.provider === provider.code,
    })),
]);

const activeSummary = computed(() => {
    const parts: string[] = [];
    if (props.filters.q !== '') {
        parts.push(`\u201c${props.filters.q}\u201d`);
    }
    for (const chip of chips.value) {
        if (chip.pressed) {
            parts.push(chip.label);
        }
    }
    return parts.join(', ');
});

function apply(): void {
    const query: Record<string, string> = {order: props.order, order_direction: props.order_direction};
    if (form.q.trim() !== '') {
        query.q = form.q.trim();
    }
    if (form.role !== '') {
        query.role = form.role;
    }
    if (form.suspended) {
        query.suspended = '1';
    }
    if (form.first_login) {
        query.first_login = '1';
    }
    if (form.provider !== '') {
        query.provider = form.provider;
    }
    router.get('/admin/users', query, {preserveState: true, replace: true});
}

function setQuery(value: string): void {
    form.q = value;
    apply();
}

function toggleChip(key: string): void {
    if (key === 'role') {
        form.role = form.role === 'admin' ? '' : 'admin';
    } else if (key === 'suspended' || key === 'first_login') {
        form[key] = !form[key];
    } else {
        const code = key.slice('provider:'.length);
        form.provider = form.provider === code ? '' : code;
    }
    apply();
}
</script>

<template>
    <AppLayout title="Users">
        <template #breadcrumbs>
            <Breadcrumbs :items="adminUserCrumbs()" />
        </template>
        <Head title="Users" />
        <div class="page-header mt-0">
            <h1>Users</h1>
        </div>

        <SearchBar
            :model-value="form.q"
            placeholder="Search by id or nickname. Try id:42 or nickname:alice"
            label="Search users"
            :result-count="users.total"
            :total="total"
            noun="users"
            :chips="chips"
            @update:model-value="setQuery"
            @submit="setQuery"
            @toggle-chip="toggleChip"
        />

        <div>
            <div>
                <div class="card">
                    <ul
                        v-if="users.data.length > 0"
                        class="list-group list-group-flush d-md-none"
                        data-test="users-list"
                    >
                        <li v-for="user in users.data" :key="user.id" class="list-group-item p-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="flex-fill min-w-0">
                                    <Link :href="`/admin/users/${user.id}`" class="fw-medium">{{ user.nickname }}</Link>
                                    <span v-if="user.is_admin" class="badge bg-primary text-primary-fg ms-1"
                                        >Admin</span
                                    >
                                    <span v-if="user.suspended" class="badge bg-red-lt ms-1">Suspended</span>
                                    <div class="text-secondary small mt-1">
                                        #{{ user.id }} &middot;
                                        <template v-if="user.last_login"
                                            >last login {{ user.last_login_human }}</template
                                        >
                                        <template v-else>never logged in</template>
                                    </div>
                                </div>
                                <Link
                                    :href="`/admin/users/${user.id}`"
                                    class="btn btn-icon btn-ghost-secondary user-open"
                                    :aria-label="`Open ${user.nickname}`"
                                >
                                    <i class="icon ti ti-chevron-right" aria-hidden="true"></i>
                                </Link>
                            </div>
                        </li>
                    </ul>
                    <div v-if="users.data.length > 0" class="table-responsive d-none d-md-block">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>
                                        <SortHeader
                                            title="ID"
                                            field="id"
                                            :order="order"
                                            :order-direction="order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Nickname"
                                            field="nickname"
                                            :order="order"
                                            :order-direction="order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Last Login"
                                            field="last_login"
                                            direction="desc"
                                            :order="order"
                                            :order-direction="order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Created"
                                            field="created_at"
                                            direction="desc"
                                            :order="order"
                                            :order-direction="order_direction"
                                        />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id">
                                    <td class="text-muted">{{ user.id }}</td>
                                    <td>
                                        <Link :href="`/admin/users/${user.id}`">{{ user.nickname }}</Link>
                                    </td>
                                    <td>
                                        <span v-if="user.last_login" :title="user.last_login">{{
                                            user.last_login_human
                                        }}</span>
                                        <span v-else class="text-muted">Never</span>
                                    </td>
                                    <td>
                                        <span :title="user.created_at">{{ user.created_at_human }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else-if="filtered"
                        icon="ti-users-off"
                        title="No matching users"
                        :description="`Nothing matches ${activeSummary}.`"
                    >
                        <Link :href="'/admin/users'" class="btn btn-primary" data-test="clear-filters"
                            >Clear filters</Link
                        >
                    </EmptyState>
                    <EmptyState
                        v-else
                        icon="ti-users-off"
                        title="No users yet"
                        description="Users appear here once they have signed in."
                    />
                    <div v-if="users.last_page > 1" class="card-footer d-flex">
                        <Pagination class="ms-auto" :paginator="users" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.user-open {
    min-inline-size: 44px;
    min-block-size: 44px;
}
</style>

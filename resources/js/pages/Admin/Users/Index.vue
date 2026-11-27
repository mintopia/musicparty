<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import {composeSearch, resolveSearch, setSingleToken} from '@/utils/searchFilters';
import type {TokenSpec} from '@/utils/searchFilters';
import RowMenu from '@/components/RowMenu.vue';
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
    avatar_url?: string;
    accounts?: {provider: string | null; name: string | null; needs_relink: boolean}[];
    parties_count?: number;
    last_login: string | null;
    last_login_human: string | null;
    created_at: string;
    created_at_human: string;
}

const props = defineProps<{
    users: Paginator<AdminUserRow> & {total?: number};
    filters: {
        q: string;
        role: string;
        suspended: boolean;
        first_login: boolean;
        provider: string;
        spotify_relink: boolean;
    };
    providers: {code: string; name: string}[];
    total: number;
    order: string;
    order_direction: 'asc' | 'desc';
}>();

const spec: TokenSpec = {
    role: ['admin'],
    suspended: ['yes'],
    signup: ['unfinished'],
    spotify: ['relink'],
    provider: null,
};
const form = reactive({
    q: composeSearch(props.filters.q, {
        role: props.filters.role,
        suspended: props.filters.suspended ? 'yes' : '',
        signup: props.filters.first_login ? 'unfinished' : '',
        spotify: props.filters.spotify_relink ? 'relink' : '',
        provider: props.filters.provider,
    }),
});

const filtered = computed(
    () =>
        props.filters.q !== '' ||
        props.filters.role !== '' ||
        props.filters.suspended ||
        props.filters.first_login ||
        props.filters.spotify_relink ||
        props.filters.provider !== '',
);
const resolved = computed(() => resolveSearch(form.q, spec));

const chips = computed<SearchChip[]>(() => {
    const active = resolved.value.filters;
    return [
        {key: 'role:admin', label: 'Admin', pressed: active.role === 'admin'},
        {key: 'suspended:yes', label: 'Suspended', pressed: active.suspended === 'yes'},
        {key: 'signup:unfinished', label: 'Signup not finished', pressed: active.signup === 'unfinished'},
        {key: 'spotify:relink', label: 'Spotify needs re-link', pressed: active.spotify === 'relink'},
        ...props.providers
            .filter((provider) => provider.code !== 'spotify')
            .map((provider) => ({
                key: `provider:${provider.code}`,
                label: provider.name,
                pressed: active.provider === provider.code,
            })),
    ];
});

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
    const {text, filters} = resolveSearch(form.q, spec);
    const query: Record<string, string> = {order: props.order, order_direction: props.order_direction};
    if (text.trim() !== '') {
        query.q = text.trim();
    }
    if (filters.role) {
        query.role = filters.role;
    }
    if (filters.suspended) {
        query.suspended = '1';
    }
    if (filters.signup) {
        query.first_login = '1';
    }
    if (filters.spotify) {
        query.spotify_relink = '1';
    }
    if (filters.provider) {
        query.provider = filters.provider;
    }
    router.get('/admin/users', query, {preserveState: true, replace: true});
}

function setQuery(value: string): void {
    form.q = value;
    apply();
}

function toggleChip(key: string): void {
    const [field, value] = key.split(':') as [string, string];
    form.q = setSingleToken(form.q, spec, field, value);
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
            placeholder="Search by id, nickname or linked account. Try role:admin or provider:discord"
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
                                            title="User"
                                            field="nickname"
                                            :order="order"
                                            :order-direction="order_direction"
                                        />
                                    </th>
                                    <th>Linked accounts</th>
                                    <th class="text-end">Parties</th>
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
                                    <th><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id">
                                    <td class="text-muted">{{ user.id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="avatar avatar-sm"
                                                :style="
                                                    user.avatar_url
                                                        ? {backgroundImage: `url(${user.avatar_url})`}
                                                        : undefined
                                                "
                                                data-test="user-avatar"
                                            ></span>
                                            <div>
                                                <Link :href="`/admin/users/${user.id}`" class="fw-medium">{{
                                                    user.nickname
                                                }}</Link>
                                                <span v-if="user.is_admin" class="badge bg-primary text-primary-fg ms-1"
                                                    >Admin</span
                                                >
                                                <span v-if="user.suspended" class="badge bg-red-lt ms-1"
                                                    >Suspended</span
                                                >
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-inline-flex gap-2" data-test="user-accounts">
                                            <i
                                                v-for="(account, index) in user.accounts ?? []"
                                                :key="index"
                                                class="ti"
                                                :class="[
                                                    `ti-brand-${account.provider}`,
                                                    account.needs_relink ? 'text-warning' : 'text-secondary',
                                                ]"
                                                role="img"
                                                :title="
                                                    account.needs_relink
                                                        ? `${account.name}, needs re-link`
                                                        : (account.name ?? '')
                                                "
                                                :aria-label="
                                                    account.needs_relink
                                                        ? `${account.name}, needs re-link`
                                                        : (account.name ?? '')
                                                "
                                            ></i>
                                        </span>
                                    </td>
                                    <td class="text-end">{{ user.parties_count ?? 0 }}</td>
                                    <td>
                                        <span v-if="user.last_login" :title="user.last_login">{{
                                            user.last_login_human
                                        }}</span>
                                        <span v-else class="text-muted">Never</span>
                                    </td>
                                    <td>
                                        <span :title="user.created_at">{{ user.created_at_human }}</span>
                                    </td>
                                    <td class="text-end">
                                        <RowMenu :label="`Actions for ${user.nickname}`">
                                            <Link class="dropdown-item" :href="`/admin/users/${user.id}`"
                                                ><i class="icon ti ti-user me-2" aria-hidden="true"></i>View</Link
                                            >
                                            <Link class="dropdown-item" :href="`/admin/users/${user.id}/edit`"
                                                ><i class="icon ti ti-edit me-2" aria-hidden="true"></i>Edit</Link
                                            >
                                            <a class="dropdown-item" :href="`/admin/users/${user.id}/impersonate`"
                                                ><i class="icon ti ti-spy me-2" aria-hidden="true"></i>Impersonate
                                                {{ user.nickname }}</a
                                            >
                                            <div class="dropdown-divider"></div>
                                            <Link
                                                class="dropdown-item text-danger"
                                                :href="`/admin/users/${user.id}/delete`"
                                                ><i class="icon ti ti-trash me-2" aria-hidden="true"></i>Delete</Link
                                            >
                                        </RowMenu>
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
                        <template #title
                            >No users match <code>{{ form.q.trim() }}</code></template
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

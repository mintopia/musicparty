<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3';
import UserBreadcrumbs from '@/components/UserBreadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface ShowUser {
    id: number;
    nickname: string;
    email: string | null;
    suspended: boolean;
    first_login: boolean;
    terms_agreed: boolean;
    roles: {name: string}[];
    last_login: string | null;
    last_login_human: string | null;
    created_at: string;
    created_at_human: string;
}

interface LinkedAccountRow {
    id: number;
    provider: string;
    name: string;
    email: string | null;
    avatar_url: string | null;
    external_id: string;
    can_delete: boolean;
}

defineProps<{user: ShowUser; accounts: LinkedAccountRow[]}>();
</script>

<template>
    <AppLayout :title="user.nickname">
        <template #breadcrumbs>
            <UserBreadcrumbs :user="user" active />
        </template>
        <Head :title="user.nickname" />
        <div class="page-header mt-0">
            <h1>{{ user.nickname }}</h1>
        </div>

        <div v-if="user.suspended" class="alert alert-warning alert-important" role="alert">
            This user is suspended. They will not be able to login.
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="datagrid">
                            <div>
                                <div class="datagrid-title">ID</div>
                                <div>{{ user.id }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Nickname</div>
                                <div>{{ user.nickname }}</div>
                            </div>
                            <div>
                                <div class="datagrid-title">Email</div>
                                <div>
                                    <a v-if="user.email" :href="`mailto:${user.email}`">{{ user.email }}</a>
                                    <span v-else class="text-muted">None</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Roles</div>
                                <div>
                                    <template v-if="user.roles.length > 0">
                                        <span
                                            v-for="role in user.roles"
                                            :key="role.name"
                                            class="badge bg-primary text-primary-fg me-1"
                                            >{{ role.name }}</span
                                        >
                                    </template>
                                    <span v-else class="text-muted">None</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Completed Signup?</div>
                                <div>
                                    <span class="status" :class="user.first_login ? 'status-red' : 'status-green'">{{
                                        user.first_login ? 'No' : 'Yes'
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Agreed Terms and Conditions</div>
                                <div>
                                    <span class="status" :class="user.terms_agreed ? 'status-green' : 'status-red'">{{
                                        user.terms_agreed ? 'Yes' : 'No'
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Suspended</div>
                                <div>
                                    <span class="status" :class="user.suspended ? 'status-red' : 'status-green'">{{
                                        user.suspended ? 'Yes' : 'No'
                                    }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Last Login</div>
                                <div>
                                    <span v-if="user.last_login" :title="user.last_login">{{
                                        user.last_login_human
                                    }}</span>
                                    <span v-else class="text-muted">Never</span>
                                </div>
                            </div>
                            <div>
                                <div class="datagrid-title">Created</div>
                                <div>
                                    <span :title="user.created_at">{{ user.created_at_human }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer align-content-end d-flex btn-list">
                        <Link :href="`/admin/users/${user.id}/delete`" class="ms-auto btn btn-outline-danger">
                            <i class="icon ti ti-trash"></i>
                            Delete
                        </Link>
                        <a :href="`/admin/users/${user.id}/impersonate`" class="btn btn-outline-primary ms-auto">
                            <i class="icon ti ti-spy"></i>
                            Impersonate
                        </a>
                        <Link :href="`/admin/users/${user.id}/edit`" class="btn btn-primary">
                            <i class="icon ti ti-edit"></i>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <h2>Linked Accounts</h2>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Provider</th>
                                    <th>Nickname</th>
                                    <th>External ID</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="account in accounts" :key="account.id">
                                    <td class="text-muted">{{ account.id }}</td>
                                    <td>{{ account.provider }}</td>
                                    <td>
                                        <div class="d-flex py-1 align-items-center">
                                            <span
                                                class="avatar me-2"
                                                :style="
                                                    account.avatar_url
                                                        ? {backgroundImage: `url('${account.avatar_url}')`}
                                                        : undefined
                                                "
                                            ></span>
                                            <div class="flex-fill">
                                                <div class="fw-medium">{{ account.name }}</div>
                                                <div class="text-secondary">{{ account.email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ account.external_id }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <Link
                                                v-if="account.can_delete"
                                                class="ms-auto btn btn-outline-danger"
                                                :href="`/admin/users/${user.id}/accounts/${account.id}`"
                                            >
                                                <i class="icon ti ti-trash"></i>
                                                Delete
                                            </Link>
                                            <span v-else class="ms-auto btn btn-outline-danger disabled">
                                                <i class="icon ti ti-trash"></i>
                                                Delete
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="accounts.length === 0">
                                    <td colspan="5" class="text-center p-4">
                                        <p>There are no linked accounts</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

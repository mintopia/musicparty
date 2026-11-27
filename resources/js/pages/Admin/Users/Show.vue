<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link} from '@inertiajs/vue3';
import StatePill from '@/components/StatePill.vue';
import PageHead from '@/components/PageHead.vue';
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

const props = defineProps<{user: ShowUser; accounts: LinkedAccountRow[]}>();

const summary = [
    `User ${props.user.id}`,
    `joined ${props.user.created_at_human}`,
    props.user.last_login ? `last login ${props.user.last_login_human}` : 'never logged in',
].join(' · ');
</script>

<template>
    <AppLayout :title="user.nickname">
        <template #breadcrumbs>
            <Breadcrumbs :items="adminUserCrumbs(user)" />
        </template>
        <Head :title="user.nickname" />
        <div class="mp-touch">
            <PageHead :title="user.nickname" :summary="summary">
                <a :href="`/admin/users/${user.id}/impersonate`" class="btn btn-outline-secondary">
                    <i class="icon ti ti-spy" aria-hidden="true"></i>
                    Impersonate {{ user.nickname }}
                </a>
                <Link :href="`/admin/users/${user.id}/edit`" class="btn btn-primary">
                    <i class="icon ti ti-edit" aria-hidden="true"></i>
                    Edit
                </Link>
            </PageHead>

            <div v-if="user.suspended" class="alert alert-warning alert-important" role="alert">
                This user is suspended. They will not be able to login.
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Account</h2>
                        </div>
                        <div class="card-body">
                            <div class="datagrid mp-datagrid">
                                <div>
                                    <div class="datagrid-title">Email</div>
                                    <div>
                                        <a v-if="user.email" :href="`mailto:${user.email}`">{{ user.email }}</a>
                                        <span v-else class="text-secondary">None</span>
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
                                        <span v-else class="text-secondary">None</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="datagrid-title">Completed signup</div>
                                    <div>
                                        <StatePill :state="user.first_login ? 'warn' : 'ok'">{{
                                            user.first_login ? 'No' : 'Yes'
                                        }}</StatePill>
                                    </div>
                                </div>
                                <div>
                                    <div class="datagrid-title">Agreed terms</div>
                                    <div>
                                        <StatePill :state="user.terms_agreed ? 'ok' : 'warn'">{{
                                            user.terms_agreed ? 'Yes' : 'No'
                                        }}</StatePill>
                                    </div>
                                </div>
                                <div>
                                    <div class="datagrid-title">Suspended</div>
                                    <div>
                                        <StatePill :state="user.suspended ? 'error' : 'ok'">{{
                                            user.suspended ? 'Yes' : 'No'
                                        }}</StatePill>
                                    </div>
                                </div>
                                <div>
                                    <div class="datagrid-title">Last login</div>
                                    <div>
                                        <span v-if="user.last_login" :title="user.last_login">{{
                                            user.last_login_human
                                        }}</span>
                                        <span v-else class="text-secondary">Never</span>
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
                        <div class="card-footer d-flex justify-content-end">
                            <Link :href="`/admin/users/${user.id}/delete`" class="btn btn-outline-danger">
                                <i class="icon ti ti-trash" aria-hidden="true"></i>
                                Delete {{ user.nickname }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Linked accounts</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Provider</th>
                                        <th>Account</th>
                                        <th>External ID</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="account in accounts" :key="account.id">
                                        <td>{{ account.provider }}</td>
                                        <td>
                                            <div class="d-flex py-1 align-items-center">
                                                <span
                                                    class="avatar avatar-sm me-2"
                                                    :style="
                                                        account.avatar_url
                                                            ? {backgroundImage: `url('${account.avatar_url}')`}
                                                            : undefined
                                                    "
                                                ></span>
                                                <div class="flex-fill">
                                                    <div class="fw-medium">{{ account.name }}</div>
                                                    <div class="text-secondary small">{{ account.email ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-secondary">{{ account.external_id }}</td>
                                        <td>
                                            <Link
                                                v-if="account.can_delete"
                                                class="btn btn-sm btn-outline-danger"
                                                :href="`/admin/users/${user.id}/accounts/${account.id}`"
                                            >
                                                <i class="icon ti ti-unlink" aria-hidden="true"></i>
                                                Unlink
                                            </Link>
                                            <span v-else class="btn btn-sm btn-outline-danger disabled">
                                                <i class="icon ti ti-unlink" aria-hidden="true"></i>
                                                Unlink
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="accounts.length === 0">
                                        <td colspan="4" class="text-center p-4">
                                            <p class="mb-0">There are no linked accounts</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.mp-datagrid {
    --tblr-datagrid-padding: 1rem;
    grid-template-columns: minmax(0, 1fr);
}
</style>

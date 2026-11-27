<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link} from '@inertiajs/vue3';
import StatePill from '@/components/StatePill.vue';
import PageHead from '@/components/PageHead.vue';
import {show as showParty} from '@/routes/parties';
import AppLayout from '@/layouts/AppLayout.vue';

function roleBadgeClass(role: string): string {
    return role === 'Owner' ? 'badge bg-blue-lt' : 'badge bg-secondary-lt';
}

interface ShowUser {
    id: number;
    nickname: string;
    email: string | null;
    suspended: boolean;
    first_login: boolean;
    terms_agreed: boolean;
    avatar_url: string;
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
    provider_code: string | null;
    linked_human: string | null;
    linked_at: string | null;
    needs_relink: boolean;
    token_expires_human: string | null;
    token_expired: boolean;
    can_delete: boolean;
}

interface PartyRow {
    code: string;
    name: string;
    role: string | null;
}

const props = defineProps<{user: ShowUser; accounts: LinkedAccountRow[]; parties: PartyRow[]}>();

function tokenNote(account: LinkedAccountRow): string | null {
    if (account.token_expires_human === null) {
        return null;
    }
    return account.token_expired
        ? `Token expired ${account.token_expires_human}`
        : `Token expires ${account.token_expires_human}`;
}

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
                <template #lead>
                    <span
                        class="avatar avatar-lg"
                        :style="{backgroundImage: `url('${user.avatar_url}')`}"
                        data-test="user-avatar"
                    ></span>
                </template>
                <Link :href="`/admin/users/${user.id}/edit`" class="btn btn-primary order-1 order-sm-2">
                    <i class="icon ti ti-edit" aria-hidden="true"></i>
                    Edit
                </Link>
                <a :href="`/admin/users/${user.id}/impersonate`" class="btn btn-outline-secondary order-2 order-sm-1">
                    <i class="icon ti ti-spy" aria-hidden="true"></i>
                    Impersonate<span class="d-none d-sm-inline">&nbsp;{{ user.nickname }}</span>
                </a>
            </PageHead>

            <div v-if="user.suspended" class="alert alert-warning alert-important" role="alert">
                This user is suspended. They will not be able to login.
            </div>

            <div class="mp-grid-5-7">
                <div class="order-2 order-lg-1">
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

                <div class="mp-stack order-1 order-lg-2">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Linked accounts</h2>
                        </div>
                        <div v-if="accounts.length === 0" class="card-body text-center">
                            <p class="mb-0">There are no linked accounts</p>
                        </div>
                        <template v-else>
                            <div class="d-none d-md-block">
                                <table class="table table-vcenter card-table" data-test="accounts-table">
                                    <thead>
                                        <tr>
                                            <th>Provider</th>
                                            <th>Account</th>
                                            <th>Linked</th>
                                            <th>State</th>
                                            <th class="w-1"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="account in accounts" :key="account.id">
                                            <td class="text-nowrap">
                                                <i
                                                    class="ti me-2"
                                                    :class="`ti-brand-${account.provider_code}`"
                                                    aria-hidden="true"
                                                ></i
                                                >{{ account.provider }}
                                            </td>
                                            <td>
                                                <div class="fw-medium">{{ account.name }}</div>
                                                <span v-if="account.email" class="mp-sub">{{ account.email }}</span>
                                            </td>
                                            <td class="text-nowrap" :title="account.linked_at ?? undefined">
                                                {{ account.linked_human }}
                                            </td>
                                            <td>
                                                <StatePill :state="account.needs_relink ? 'warn' : 'ok'">{{
                                                    account.needs_relink ? 'Needs re-link' : 'Working'
                                                }}</StatePill>
                                                <span v-if="tokenNote(account)" class="mp-sub" data-test="token-note">{{
                                                    tokenNote(account)
                                                }}</span>
                                            </td>
                                            <td class="text-end">
                                                <Link
                                                    v-if="account.can_delete"
                                                    class="btn btn-sm"
                                                    :href="`/admin/users/${user.id}/accounts/${account.id}`"
                                                >
                                                    Unlink
                                                </Link>
                                                <span v-else class="btn btn-sm disabled">Unlink</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <ul class="list-group list-group-flush d-md-none" data-test="accounts-list">
                                <li v-for="account in accounts" :key="account.id" class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span
                                            ><i
                                                class="ti me-2"
                                                :class="`ti-brand-${account.provider_code}`"
                                                aria-hidden="true"
                                            ></i
                                            >{{ account.provider }}</span
                                        >
                                        <StatePill :state="account.needs_relink ? 'warn' : 'ok'">{{
                                            account.needs_relink ? 'Needs re-link' : 'Working'
                                        }}</StatePill>
                                    </div>
                                    <span class="mp-sub text-wrap"
                                        >{{ account.name }} &middot; linked {{ account.linked_human
                                        }}<template v-if="tokenNote(account)">
                                            &middot; {{ tokenNote(account) }}</template
                                        ></span
                                    >
                                    <div class="mt-2">
                                        <Link
                                            v-if="account.can_delete"
                                            class="btn btn-sm"
                                            :href="`/admin/users/${user.id}/accounts/${account.id}`"
                                        >
                                            Unlink
                                        </Link>
                                        <span v-else class="btn btn-sm disabled">Unlink</span>
                                    </div>
                                </li>
                            </ul>
                        </template>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Parties</h2>
                        </div>
                        <div v-if="parties.length === 0" class="card-body text-center">
                            <p class="mb-0 text-secondary">Not a member of any party</p>
                        </div>
                        <template v-else>
                            <table class="table table-vcenter card-table d-none d-md-table" data-test="parties-table">
                                <thead>
                                    <tr>
                                        <th>Party</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="party in parties" :key="party.code">
                                        <td>
                                            <Link :href="showParty(party.code).url">{{ party.name }}</Link>
                                            <span class="mp-sub">{{ party.code }}</span>
                                        </td>
                                        <td>
                                            <span v-if="party.role" :class="roleBadgeClass(party.role)">{{
                                                party.role
                                            }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <ul class="list-group list-group-flush d-md-none" data-test="parties-list">
                                <li
                                    v-for="party in parties"
                                    :key="party.code"
                                    class="list-group-item p-3 d-flex justify-content-between gap-2"
                                >
                                    <span class="min-w-0">
                                        <Link :href="showParty(party.code).url">{{ party.name }}</Link>
                                        <span class="mp-sub">{{ party.code }}</span>
                                    </span>
                                    <span
                                        v-if="party.role"
                                        :class="roleBadgeClass(party.role)"
                                        class="align-self-start"
                                        >{{ party.role }}</span
                                    >
                                </li>
                            </ul>
                        </template>
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

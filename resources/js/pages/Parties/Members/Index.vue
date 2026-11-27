<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchBar from '@/components/SearchBar.vue';
import type {SearchChip} from '@/components/SearchBar.vue';
import RowMenu from '@/components/RowMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState, Paginator, When} from '@/types';
import {hasToken, parseSearch, serializeSearch, toggleToken} from '@/utils/searchTokens';

interface MemberRow {
    id: number;
    nickname: string;
    role: string;
    roleCode?: string;
    createdAt: When | null;
    requests?: number;
    votes?: number;
    flagged?: number;
    isYou?: boolean;
    url: string;
    editUrl?: string;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean; ownerName?: string | null};
    playerState: LegacyPartyState;
    roles: {code: string; name: string; count?: number}[];
    memberTotal?: number;
    filters: {
        q?: string | null;
        per_page?: number | string;
        order: string;
        order_direction: 'asc' | 'desc';
    };
    members: Paginator<MemberRow> & {total: number};
}>();

const roleBadges: Record<string, string> = {
    owner: 'bg-azure-lt',
    moderator: 'bg-purple-lt',
    vip: 'bg-yellow-lt',
    user: 'bg-secondary-lt',
    banned: 'bg-red-lt',
};

function roleBadge(role: string): string {
    return roleBadges[role.toLowerCase()] ?? 'bg-secondary-lt';
}

const usersUrl = `/parties/${props.party.code}/users`;

const query = ref(props.filters.q ?? '');

watch(
    () => props.filters.q,
    (value) => {
        query.value = value ?? '';
    },
);

const filtered = computed(() => query.value.trim() !== '');

const noun = computed(() => (filtered.value ? '' : 'members'));

const chips = computed<SearchChip[]>(() =>
    props.roles.map((role) => ({
        key: role.code,
        label: role.name,
        count: role.count,
        pressed: hasToken(query.value, 'role', role.code) || hasToken(query.value, 'role', role.name),
    })),
);

function visit(q: string): void {
    const next: Record<string, string | number> = {
        order: props.filters.order,
        order_direction: props.filters.order_direction,
    };
    if (props.filters.per_page !== undefined) {
        next.per_page = props.filters.per_page;
    }
    if (q.trim() !== '') {
        next.q = q.trim();
    }
    router.get(usersUrl, next);
}

function onSearch(value: string): void {
    query.value = value;
    visit(value);
}

function toggleChip(code: string): void {
    const role = props.roles.find((candidate) => candidate.code === code);
    let next = query.value;
    for (const value of [code, role?.name ?? code]) {
        if (hasToken(next, 'role', value)) {
            next = toggleToken(next, 'role', value);
            query.value = serializeSearch(parseSearch(next, ['role']));
            visit(query.value);
            return;
        }
    }
    query.value = serializeSearch(parseSearch(toggleToken(next, 'role', code), ['role']));
    visit(query.value);
}

function initials(name: string): string {
    return name.slice(0, 2).toUpperCase();
}
</script>

<template>
    <AppLayout :title="`Members - ${party.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...partyCrumbs(party), {label: 'Members'}]" />
        </template>
        <template #header>
            <PartyPlayer :code="party.code" :initial-state="playerState" />
        </template>
        <Head :title="`Members - ${party.name}`" />
        <div class="mp-page-head">
            <div>
                <h1>Members</h1>
                <p v-if="party.canManage">
                    Everyone who has joined {{ party.name }}. Owners change roles; Moderators can look but not edit.
                </p>
                <p v-else>Everyone who has joined {{ party.name }}.</p>
            </div>
        </div>

        <div
            v-if="!party.canManage"
            class="alert alert-info d-flex gap-3 align-items-center"
            role="status"
            data-test="members-readonly"
        >
            <i class="icon ti ti-eye" aria-hidden="true"></i>
            <div>
                <b>Read-only for Moderators.</b> You can see everyone and their activity. Only the owner can change
                roles or ban members.
            </div>
        </div>

        <SearchBar
            :model-value="query"
            placeholder="Search members, or type role:vip"
            label="Search members"
            :result-count="members.total"
            :total="memberTotal"
            :noun="noun"
            :chips="chips"
            @update:model-value="onSearch"
            @toggle-chip="toggleChip"
        />
        <div class="card">
            <ul v-if="members.data.length > 0" class="list-group list-group-flush d-md-none" data-test="members-list">
                <li v-for="member in members.data" :key="member.id" class="list-group-item p-3" data-test="member-card">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="avatar">{{ initials(member.nickname) }}</span>
                        <div class="flex-fill min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <Link :href="member.url" class="fw-semibold">{{ member.nickname }}</Link>
                                <span class="badge" :class="roleBadge(member.roleCode ?? member.role)">{{
                                    member.role
                                }}</span>
                                <span v-if="member.isYou" class="badge bg-secondary-lt">You</span>
                            </div>
                            <div class="text-secondary small mt-1">
                                {{ member.requests ?? 0 }} Requests &middot; {{ member.votes ?? 0 }} votes
                                <template v-if="member.createdAt"
                                    >&middot; joined {{ member.createdAt.human }}</template
                                >
                            </div>
                        </div>
                        <RowMenu v-if="party.canManage" :label="`Actions for ${member.nickname}`">
                            <Link class="dropdown-item" :href="member.editUrl ?? `${member.url}/edit`"
                                ><i class="icon ti ti-user-cog me-2" aria-hidden="true"></i>Change role</Link
                            >
                            <Link class="dropdown-item" :href="member.url"
                                ><i class="icon ti ti-list-details me-2" aria-hidden="true"></i>View activity</Link
                            >
                            <div class="dropdown-divider"></div>
                            <Link class="dropdown-item text-danger" :href="member.editUrl ?? `${member.url}/edit`"
                                ><i class="icon ti ti-ban me-2" aria-hidden="true"></i>Ban {{ member.nickname }}</Link
                            >
                        </RowMenu>
                    </div>
                </li>
            </ul>
            <div v-if="members.data.length > 0" class="d-none d-md-block">
                <table class="table table-hover table-vcenter card-table mp-members">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th class="text-end">Requests</th>
                            <th class="text-end">Votes</th>
                            <th class="text-end">Flagged</th>
                            <th>Joined</th>
                            <th v-if="party.canManage" class="w-1">
                                <span class="visually-hidden">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members.data" :key="member.id" data-test="member-row">
                            <td>
                                <div class="mp-who">
                                    <span class="avatar avatar-sm">{{ initials(member.nickname) }}</span>
                                    <Link :href="member.url" class="fw-semibold">{{ member.nickname }}</Link>
                                    <span v-if="member.isYou" class="badge bg-secondary-lt">You</span>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="badge"
                                    :class="roleBadge(member.roleCode ?? member.role)"
                                    data-test="role-badge"
                                    >{{ member.role }}</span
                                >
                            </td>
                            <td class="mp-num text-end">{{ member.requests ?? 0 }}</td>
                            <td class="mp-num text-end">{{ member.votes ?? 0 }}</td>
                            <td class="mp-num text-end" data-test="flagged-count">
                                <span :class="(member.flagged ?? 0) > 0 ? 'text-orange' : 'text-secondary'">{{
                                    member.flagged ?? 0
                                }}</span>
                            </td>
                            <td>
                                <span v-if="member.createdAt" :title="member.createdAt.iso">{{
                                    member.createdAt.human
                                }}</span>
                            </td>
                            <td v-if="party.canManage" class="text-end">
                                <RowMenu :label="`Actions for ${member.nickname}`">
                                    <Link class="dropdown-item" :href="member.editUrl ?? `${member.url}/edit`"
                                        ><i class="icon ti ti-user-cog me-2" aria-hidden="true"></i>Change role</Link
                                    >
                                    <Link class="dropdown-item" :href="member.url"
                                        ><i class="icon ti ti-list-details me-2" aria-hidden="true"></i>View
                                        activity</Link
                                    >
                                    <div class="dropdown-divider"></div>
                                    <Link
                                        class="dropdown-item text-danger"
                                        :href="member.editUrl ?? `${member.url}/edit`"
                                        data-test="ban-member"
                                        ><i class="icon ti ti-ban me-2" aria-hidden="true"></i>Ban
                                        {{ member.nickname }}</Link
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
                :title="`No members match ${query.trim()}`"
                description="Remove a filter to see more members."
            >
                <template #title
                    >No members match <code>{{ query.trim() }}</code></template
                >
                <Link :href="usersUrl" class="btn btn-primary">Clear filters</Link>
            </EmptyState>
            <EmptyState
                v-else
                icon="ti-users-off"
                title="No members yet"
                description="People who join this party will appear here."
            />
            <div v-if="members.last_page > 1" class="card-footer d-flex">
                <Pagination class="ms-auto" :paginator="members" />
            </div>
        </div>
    </AppLayout>
</template>

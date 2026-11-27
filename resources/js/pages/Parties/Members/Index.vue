<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {partyCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import Pagination from '@/components/Pagination.vue';
import PartyPlayer from '@/components/PartyPlayer.vue';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';
import SortHeader from '@/components/SortHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {LegacyPartyState, Paginator, When} from '@/types';

interface MemberRow {
    id: number;
    nickname: string;
    role: string;
    createdAt: When | null;
    url: string;
}

const props = defineProps<{
    party: {code: string; name: string; canManage: boolean};
    playerState: LegacyPartyState;
    roles: {code: string; name: string}[];
    filters: {
        nickname?: string | null;
        role?: string | null;
        per_page?: number | string;
        order: string;
        order_direction: 'asc' | 'desc';
    };
    members: Paginator<MemberRow>;
}>();

const form = reactive({nickname: props.filters.nickname ?? '', role: props.filters.role ?? ''});
const usersUrl = `/parties/${props.party.code}/users`;

const filtered = computed(() => Boolean(props.filters.nickname || props.filters.role));

function submit(): void {
    const query: Record<string, string | number> = {
        order: props.filters.order,
        order_direction: props.filters.order_direction,
    };
    if (props.filters.per_page !== undefined) {
        query.per_page = props.filters.per_page;
    }
    if (form.nickname) {
        query.nickname = form.nickname;
    }
    if (form.role) {
        query.role = form.role;
    }
    router.get(usersUrl, query);
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
        <div class="page-header mt-0">
            <h1>Members</h1>
        </div>

        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form :action="usersUrl" method="get" class="card" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                    </div>
                    <div class="card-body">
                        <SearchTextField v-model="form.nickname" name="Nickname" property="nickname" />
                        <SearchSelectField
                            v-model="form.role"
                            name="Role"
                            property="role"
                            :options="roles"
                            value-key="code"
                            label-key="name"
                        />
                    </div>
                    <div class="card-footer d-flex">
                        <button class="btn btn-primary ms-auto" type="submit">Search</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-9 col-md-12">
                <div class="card">
                    <div v-if="members.data.length > 0" class="table-responsive">
                        <table class="table table-hover table-vcenter text-nowrap card-table">
                            <thead>
                                <tr>
                                    <th>
                                        <SortHeader
                                            title="Nickname"
                                            field="nickname"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Role"
                                            field="role"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                    <th>
                                        <SortHeader
                                            title="Joined"
                                            field="created_at"
                                            direction="desc"
                                            :order="filters.order"
                                            :order-direction="filters.order_direction"
                                        />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="member in members.data" :key="member.id" data-test="member-row">
                                    <td>
                                        <Link :href="member.url">{{ member.nickname }}</Link>
                                    </td>
                                    <td>{{ member.role }}</td>
                                    <td>
                                        <span v-if="member.createdAt" :title="member.createdAt.iso">{{
                                            member.createdAt.human
                                        }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else-if="filtered"
                        icon="ti-users-off"
                        title="No matching members"
                        description="Try a different nickname or role."
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
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import Pagination from '@/components/Pagination.vue';
import SearchTextField from '@/components/SearchTextField.vue';
import SortHeader from '@/components/SortHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {Paginator} from '@/types';

interface AdminUserRow {
    id: number;
    nickname: string;
    last_login: string | null;
    last_login_human: string | null;
    created_at: string;
    created_at_human: string;
}

const props = defineProps<{
    users: Paginator<AdminUserRow>;
    filters: {id: string | null; nickname: string | null};
    order: string;
    order_direction: 'asc' | 'desc';
}>();

const search = reactive({id: props.filters.id, nickname: props.filters.nickname});

const filtered = computed(() => Boolean(props.filters.id || props.filters.nickname));

function submit(): void {
    const query: Record<string, string> = {order: props.order, order_direction: props.order_direction};
    if (search.id) {
        query.id = search.id;
    }
    if (search.nickname) {
        query.nickname = search.nickname;
    }
    router.get('/admin/users', query);
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

        <div class="row">
            <div class="col-md-12 col-lg-3 mb-4">
                <form class="card" @submit.prevent="submit">
                    <div class="card-header">
                        <h3 class="card-title">Search</h3>
                    </div>
                    <div class="card-body">
                        <SearchTextField v-model="search.id" name="ID" property="id" />
                        <SearchTextField v-model="search.nickname" name="Nickname" property="nickname" />
                    </div>
                    <div class="card-footer d-flex">
                        <button class="btn btn-primary ms-auto" type="submit">Search</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-9 col-md-12">
                <div class="card">
                    <div v-if="users.data.length > 0" class="table-responsive">
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
                        description="Try a different ID or nickname."
                    >
                        <Link :href="'/admin/users'" class="btn btn-primary">Clear filters</Link>
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

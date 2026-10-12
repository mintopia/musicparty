<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref} from 'vue';
import DataTable from '../../Components/ui/DataTable.vue';
import Pagination from '../../Components/ui/Pagination.vue';
import RelativeTime from '../../Components/ui/RelativeTime.vue';
import SelectField from '../../Components/ui/SelectField.vue';

const props = defineProps({
    party: {type: Object, required: true},
    member: {type: Object, required: true},
    requests: {type: Array, default: () => []},
    votes: {type: Object, required: true},
    filters: {type: Object, default: () => ({})},
    abilities: {type: Object, required: true},
});

const page = usePage();
const memberError = computed(() => page.props.errors?.member ?? null);
const roleError = computed(() => page.props.errors?.role ?? null);

const assignableRoles = [
    {value: 'moderator', label: 'Moderator'},
    {value: 'vip', label: 'VIP'},
    {value: 'guest', label: 'Guest'},
];
const roleLabels = {host: 'Host', moderator: 'Moderator', vip: 'VIP', guest: 'Guest'};
const voteColumns = [
    {key: 'track', label: 'Track'},
    {key: 'vote', label: 'Vote'},
    {key: 'when', label: 'Voted'},
];
const requestColumns = [
    {key: 'track', label: 'Track'},
    {key: 'status', label: 'Status'},
    {key: 'when', label: 'Requested'},
];

const searchText = ref(props.filters?.search ?? '');
let searchTimer = null;
const base = computed(() => `/parties/${props.party.code}/members/${props.member.id}`);

const onSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const data = searchText.value ? {search: searchText.value} : {};
        router.get(base.value, data, {preserveState: true, preserveScroll: true});
    }, 300);
};
onBeforeUnmount(() => clearTimeout(searchTimer));

const busy = ref(false);
const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
};
const changeRole = (role) => {
    if (role !== props.member.role) {
        router.put(`${base.value}/role`, {role}, options);
    }
};
const ban = () => router.put(`${base.value}/ban`, {}, options);
const unban = () => router.delete(`${base.value}/ban`, options);

const artistLine = (artists) => (artists ?? []).join(', ');
const voteLabel = (value) => (value > 0 ? 'Up' : 'Down');
const initial = computed(() => props.member.nickname?.charAt(0).toUpperCase() ?? '?');
</script>

<template>
    <Head :title="`${member.nickname} - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <div class="flex items-center gap-3">
                <img
                    v-if="member.avatar"
                    :src="member.avatar"
                    :alt="`${member.nickname} avatar`"
                    class="size-12 rounded-full border border-border object-cover"
                >
                <span
                    v-else
                    aria-hidden="true"
                    class="flex size-12 items-center justify-center rounded-full border border-border text-base font-semibold"
                >{{ initial }}</span>
                <h1 class="text-xl font-semibold" data-testid="member-nickname">{{ member.nickname }}</h1>
            </div>
            <Link
                :href="`/parties/${party.code}/members`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to Members</Link>
        </header>

        <p v-if="memberError" role="alert" data-testid="member-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ memberError }}</p>
        <p v-if="roleError" role="alert" data-testid="member-role-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ roleError }}</p>

        <section class="flex flex-wrap items-center gap-4 rounded border border-border bg-surface px-5 py-5">
            <div class="flex flex-col gap-1">
                <span class="text-xs uppercase tracking-wide text-muted">Role</span>
                <div v-if="abilities.canChangeRoles" class="flex items-center gap-2">
                    <span class="sr-only">Role for {{ member.nickname }}</span>
                    <SelectField
                        :id="`member-role-${member.id}`"
                        :model-value="member.role"
                        :options="assignableRoles"
                        :disabled="busy"
                        @update:model-value="changeRole"
                    />
                </div>
                <span v-else data-testid="member-role" class="w-fit rounded border border-border px-2 py-0.5 text-xs font-medium">{{ roleLabels[member.role] ?? member.role }}</span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-xs uppercase tracking-wide text-muted">Joined</span>
                <span data-testid="member-joined" class="text-sm"><RelativeTime :value="member.joinedAt" /></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="member.banned"
                    data-testid="member-banned"
                    class="rounded border border-red-500 px-2 py-0.5 text-xs font-medium text-red-500"
                >Banned</span>
                <template v-if="abilities.canBan">
                    <button
                        v-if="member.banned"
                        type="button"
                        :disabled="busy"
                        data-testid="member-unban"
                        class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary disabled:opacity-50"
                        @click="unban"
                    >Unban</button>
                    <button
                        v-else
                        type="button"
                        :disabled="busy"
                        data-testid="member-ban"
                        class="min-h-11 rounded border border-red-500 px-3 text-sm text-red-500 disabled:opacity-50"
                        @click="ban"
                    >Ban</button>
                </template>
            </div>
        </section>

        <section class="overflow-hidden rounded border border-border bg-surface">
            <h2 class="px-5 pt-5 text-lg font-semibold">Requests</h2>
            <DataTable
                :columns="requestColumns"
                :rows="requests"
                caption="Requests"
                empty-text="No requests."
                :row-attrs="() => ({'data-testid': 'member-request-row'})"
            >
                <template #empty><span data-testid="member-requests-empty">No requests.</span></template>
                <template #row="{row: request}">
                    <td class="px-5 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ request.title }}</span>
                            <span class="text-xs text-muted">{{ artistLine(request.artists) }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 capitalize">{{ request.status }}</td>
                    <td class="px-5 py-3 text-muted"><RelativeTime :value="request.requestedAt" /></td>
                </template>
            </DataTable>
        </section>

        <section class="flex flex-wrap items-end gap-4 rounded border border-border bg-surface px-5 py-5">
            <div class="flex min-w-48 flex-1 flex-col gap-1">
                <label for="member-votes-search" class="text-sm font-medium">Search votes</label>
                <input
                    id="member-votes-search"
                    v-model="searchText"
                    type="search"
                    placeholder="Track"
                    data-testid="member-votes-search"
                    class="min-h-11 w-full rounded border border-border bg-background px-3 text-sm"
                    @input="onSearch"
                >
            </div>
        </section>

        <section class="overflow-hidden rounded border border-border bg-surface">
            <h2 class="px-5 pt-5 text-lg font-semibold">Votes</h2>
            <DataTable
                :columns="voteColumns"
                :rows="votes.data ?? []"
                caption="Votes"
                empty-text="No votes found."
                :row-attrs="() => ({'data-testid': 'member-vote-row'})"
            >
                <template #empty><span data-testid="member-votes-empty">No votes found.</span></template>
                <template #row="{row: vote}">
                    <td class="px-5 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ vote.title }}</span>
                            <span class="text-xs text-muted">{{ artistLine(vote.artists) }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3" data-testid="member-vote-value">{{ voteLabel(vote.value) }}</td>
                    <td class="px-5 py-3 text-muted"><RelativeTime :value="vote.votedAt" /></td>
                </template>
            </DataTable>
            <Pagination :paginator="votes" />
        </section>
    </div>
</template>

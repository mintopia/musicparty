<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, reactive, ref} from 'vue';
import ModSlot from '../../Components/ModSlot.vue';
import DataTable from '../../Components/ui/DataTable.vue';
import RelativeTime from '../../Components/ui/RelativeTime.vue';
import Pagination from '../../Components/ui/Pagination.vue';
import SelectField from '../../Components/ui/SelectField.vue';

const props = defineProps({
    party: {type: Object, required: true},
    members: {type: Object, required: true},
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
const roleFilterOptions = [{value: '', label: 'All roles'}, {value: 'host', label: 'Host'}, ...assignableRoles];
const columns = [
    {key: 'nickname', label: 'Nickname', sortable: true},
    {key: 'role', label: 'Role', sortable: true},
    {key: 'joined', label: 'Joined', sortable: true},
    {key: 'ban', label: 'Ban'},
];

const searchText = ref(props.filters?.search ?? '');
const roleFilter = ref(props.filters?.role ?? '');
let searchTimer = null;

const navigate = (overrides = {}) => {
    const next = {
        search: searchText.value,
        role: roleFilter.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        ...overrides,
    };
    const data = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    router.get(`/parties/${props.party.code}/members`, data, {preserveState: true, preserveScroll: true});
};

const onSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => navigate(), 300);
};
const onRole = (role) => {
    roleFilter.value = role;
    navigate();
};

const roleLabels = {host: 'Host', moderator: 'Moderator', vip: 'VIP', guest: 'Guest'};
const roleLabel = (role) => roleLabels[role] ?? role;

const presence = reactive({});
const presenceState = (member) => presence[member.nickname] ?? null;
const presenceLabel = (member) => {
    const state = presenceState(member);
    if (state === 'joining') {
        return 'Joining';
    }
    if (state === 'leaving') {
        return 'Leaving';
    }
    return state === 'here' ? 'Online' : 'Offline';
};

const channelName = `party.${props.party.code}.members`;
const timers = new Set();
const settle = (nickname, state, next) => {
    presence[nickname] = state;
    const timer = setTimeout(() => {
        timers.delete(timer);
        if (next === null) {
            delete presence[nickname];
        } else {
            presence[nickname] = next;
        }
    }, 1500);
    timers.add(timer);
};

onMounted(() => {
    window.Echo?.join(channelName)
        .here((users) => {
            users.forEach((user) => {
                presence[user.nickname] = 'here';
            });
        })
        .joining((user) => settle(user.nickname, 'joining', 'here'))
        .leaving((user) => settle(user.nickname, 'leaving', null));
});

onBeforeUnmount(() => {
    timers.forEach(clearTimeout);
    timers.clear();
    clearTimeout(searchTimer);
    window.Echo?.leave(channelName);
});

const busy = ref(false);
const base = (member) => `/parties/${props.party.code}/members/${member.id}`;
const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
};

const changeRole = (member, role) => {
    if (role === member.role) {
        return;
    }
    router.put(`${base(member)}/role`, {role}, options);
};
const ban = (member) => router.put(`${base(member)}/ban`, {}, options);
const unban = (member) => router.delete(`${base(member)}/ban`, options);

const canManageRole = (member) => props.abilities.canChangeRoles && member.role !== 'host' && !member.isYou;
const canModerate = (member) => props.abilities.canBan && member.role !== 'host' && !member.isYou;
const memberRows = computed(() => props.members.data ?? []);
const initial = (member) => member.nickname?.charAt(0).toUpperCase() ?? '?';
</script>

<template>
    <Head :title="`Members - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Members</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <p v-if="memberError" role="alert" data-testid="members-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ memberError }}</p>
        <p v-if="roleError" role="alert" data-testid="members-role-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ roleError }}</p>

        <section class="flex flex-wrap items-end gap-4 rounded border border-border bg-surface px-5 py-5">
            <div class="flex min-w-48 flex-1 flex-col gap-1">
                <label for="members-search" class="text-sm font-medium">Search</label>
                <input
                    id="members-search"
                    v-model="searchText"
                    type="search"
                    placeholder="Nickname"
                    data-testid="members-search"
                    class="min-h-11 w-full rounded border border-border bg-background px-3 text-sm"
                    @input="onSearch"
                >
            </div>
            <div class="flex flex-col gap-1">
                <label for="members-role-filter" class="text-sm font-medium">Role</label>
                <SelectField id="members-role-filter" :model-value="roleFilter" :options="roleFilterOptions" @update:model-value="onRole" />
            </div>
        </section>

        <section class="overflow-hidden rounded border border-border bg-surface">
            <DataTable
                :columns="columns"
                :rows="memberRows"
                default-order="nickname"
                order-param="sort"
                direction-param="direction"
                caption="Members"
                empty-text="No members found."
                :row-attrs="() => ({'data-testid': 'member-row'})"
            >
                <template #empty><span data-testid="members-empty">No members found.</span></template>
                <template #row="{row: member}">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="`${member.nickname} avatar`"
                                class="size-10 rounded-full border border-border object-cover"
                            >
                            <span
                                v-else
                                aria-hidden="true"
                                class="flex size-10 items-center justify-center rounded-full border border-border text-sm font-semibold"
                            >{{ initial(member) }}</span>
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="flex flex-wrap items-center gap-2 font-medium">
                                    <Link
                                        :href="`/parties/${party.code}/members/${member.id}`"
                                        data-testid="member-link"
                                        class="truncate hover:text-primary"
                                    >{{ member.nickname }}</Link>
                                    <span v-if="member.isYou" data-testid="member-you" class="text-xs text-muted">(you)</span>
                                </span>
                                <span
                                    data-testid="member-presence"
                                    :data-state="presenceState(member) ?? 'offline'"
                                    class="flex items-center gap-2 text-xs text-muted"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="size-2 rounded-full"
                                        :class="presenceState(member) === 'here' || presenceState(member) === 'joining' ? 'bg-green-500' : 'bg-border'"
                                    />
                                    {{ presenceLabel(member) }}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <div v-if="canManageRole(member)" class="flex items-center gap-2">
                            <span class="sr-only">Role for {{ member.nickname }}</span>
                            <SelectField
                                :id="`member-role-${member.id}`"
                                :model-value="member.role"
                                :options="assignableRoles"
                                :disabled="busy"
                                @update:model-value="(role) => changeRole(member, role)"
                            />
                        </div>
                        <span v-else data-testid="member-role" class="rounded border border-border px-2 py-0.5 text-xs font-medium">{{ roleLabel(member.role) }}</span>
                    </td>
                    <td class="px-5 py-3 text-muted" data-testid="member-joined"><RelativeTime :value="member.joinedAt" /></td>
                    <td class="px-5 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="member.banned"
                                data-testid="member-banned"
                                class="rounded border border-red-500 px-2 py-0.5 text-xs font-medium text-red-500"
                            >Banned</span>
                            <template v-if="canModerate(member)">
                                <button
                                    v-if="member.banned"
                                    type="button"
                                    :disabled="busy"
                                    data-testid="member-unban"
                                    class="min-h-11 rounded border border-border px-3 text-sm hover:text-primary disabled:opacity-50"
                                    @click="unban(member)"
                                >Unban</button>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="busy"
                                    data-testid="member-ban"
                                    class="min-h-11 rounded border border-red-500 px-3 text-sm text-red-500 disabled:opacity-50"
                                    @click="ban(member)"
                                >Ban</button>
                            </template>
                        </div>
                    </td>
                    <ModSlot name="members.columns" :member="member" />
                </template>
            </DataTable>
            <Pagination :paginator="members" />
        </section>
    </div>
</template>

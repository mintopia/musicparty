<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, reactive, ref} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    members: {type: Array, required: true},
    abilities: {type: Object, required: true},
});

const page = usePage();
const memberError = computed(() => page.props.errors?.member ?? null);
const roleError = computed(() => page.props.errors?.role ?? null);
const successMessage = computed(() => page.props.flash?.successMessage ?? null);

const assignableRoles = [
    {value: 'moderator', label: 'Moderator'},
    {value: 'vip', label: 'VIP'},
    {value: 'guest', label: 'Guest'},
];
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

        <p v-if="successMessage" role="status" data-testid="members-success" class="rounded border border-border bg-surface px-5 py-3 text-sm">{{ successMessage }}</p>
        <p v-if="memberError" role="alert" data-testid="members-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ memberError }}</p>
        <p v-if="roleError" role="alert" data-testid="members-role-error" class="rounded border border-border bg-surface px-5 py-3 text-sm text-red-500">{{ roleError }}</p>

        <section class="rounded border border-border bg-surface px-5 py-5">
            <p v-if="members.length === 0" data-testid="members-empty" class="text-sm text-muted">No members yet.</p>
            <ul v-else class="flex flex-col divide-y divide-border">
                <li
                    v-for="member in members"
                    :key="member.id"
                    data-testid="member-row"
                    class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0"
                >
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

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="flex flex-wrap items-center gap-2 text-sm font-medium">
                            <span class="truncate">{{ member.nickname }}</span>
                            <span v-if="member.isYou" data-testid="member-you" class="text-xs text-muted">(you)</span>
                            <span data-testid="member-role" class="rounded border border-border px-2 py-0.5 text-xs font-medium">{{ roleLabel(member.role) }}</span>
                            <span
                                v-if="member.banned"
                                data-testid="member-banned"
                                class="rounded border border-red-500 px-2 py-0.5 text-xs font-medium text-red-500"
                            >Banned</span>
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

                    <div v-if="canManageRole(member) || canModerate(member)" class="flex flex-wrap items-center gap-2">
                        <label v-if="canManageRole(member)" class="flex items-center gap-2 text-sm">
                            <span class="sr-only">Role for {{ member.nickname }}</span>
                            <select
                                :value="member.role"
                                :disabled="busy"
                                data-testid="member-role-select"
                                class="min-h-11 rounded border border-border bg-surface px-2 text-sm"
                                @change="changeRole(member, $event.target.value)"
                            >
                                <option v-for="role in assignableRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                        </label>
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
                </li>
            </ul>
        </section>
    </div>
</template>

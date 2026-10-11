<script setup>
import {Link, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Icon from './Icon.vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const user = computed(() => page.props.auth.user);
const parties = computed(() => page.props.parties);
const path = computed(() => page.url.split('?')[0]);

const isActive = (href) => path.value === href;
const isPartyActive = (code) => path.value.startsWith(`/parties/${code}`);

const manuallyOpened = ref({});
const isOpen = (code) => manuallyOpened.value[code] ?? isPartyActive(code);
const toggle = (code) => {
    manuallyOpened.value = {...manuallyOpened.value, [code]: !isOpen(code)};
};

const adminLinks = computed(() => page.props.adminLinks ?? []);
const adminItems = [
    {label: 'Dashboard', href: '/admin', exact: true},
    {label: 'Users', href: '/admin/users'},
    {label: 'Parties', href: '/admin/parties'},
    {label: 'Settings', href: '/admin/settings', also: ['/admin/theme', '/admin/providers']},
    {label: 'Tokens', href: '/admin/tokens'},
];
const adminOpened = ref(null);
const isAdminPath = computed(() => path.value === '/admin' || path.value.startsWith('/admin/'));
const adminOpen = computed(() => adminOpened.value ?? isAdminPath.value);
const isAdminItemActive = (item) => (item.exact
    ? path.value === item.href
    : [item.href, ...(item.also ?? [])].some((prefix) => path.value.startsWith(prefix)));

const itemClass = (active) => [
    'flex w-full items-center gap-2.5 border-l-[3px] px-4 py-2 text-sm hover:text-sidebar-active',
    active ? 'border-primary text-sidebar-active' : 'border-transparent',
];
</script>

<template>
    <div class="flex h-14 items-center justify-center text-xl font-semibold text-sidebar-active">
        {{ appName }}
    </div>
    <nav class="flex flex-col py-2" aria-label="Primary">
        <Link href="/" :class="itemClass(isActive('/'))" :aria-current="isActive('/') ? 'page' : undefined">
            <Icon name="home" />Home
        </Link>

        <template v-if="user">
            <div v-for="party in parties" :key="party.code">
                <button
                    type="button"
                    :class="itemClass(isPartyActive(party.code))"
                    :aria-expanded="isOpen(party.code)"
                    @click="toggle(party.code)"
                >
                    <Icon name="playlist" />
                    <span class="flex-1 text-left">{{ party.name }}</span>
                    <Icon name="chevron" class="!h-4 !w-4" />
                </button>
                <div v-if="isOpen(party.code)" class="flex flex-col">
                    <Link
                        :href="`/parties/${party.code}`"
                        :class="[itemClass(isActive(`/parties/${party.code}`)), '!pl-11']"
                    >Queue</Link>
                    <Link
                        :href="`/parties/${party.code}/search`"
                        :class="[itemClass(isActive(`/parties/${party.code}/search`)), '!pl-11']"
                    >Search</Link>
                    <Link
                        :href="`/parties/${party.code}/history`"
                        :class="[itemClass(isActive(`/parties/${party.code}/history`)), '!pl-11']"
                    >History</Link>
                    <Link
                        :href="`/parties/${party.code}/party`"
                        :class="[itemClass(isActive(`/parties/${party.code}/party`)), '!pl-11']"
                    >Party</Link>
                </div>
            </div>

            <div v-if="user.is_admin">
                <button
                    type="button"
                    data-testid="admin-group"
                    :class="itemClass(isAdminPath)"
                    :aria-expanded="adminOpen"
                    @click="adminOpened = !adminOpen"
                >
                    <Icon name="settings" />
                    <span class="flex-1 text-left">Admin</span>
                    <Icon name="chevron" class="!h-4 !w-4" />
                </button>
                <div v-if="adminOpen" class="flex flex-col">
                    <Link
                        v-for="item in adminItems"
                        :key="item.href"
                        :href="item.href"
                        :class="[itemClass(isAdminItemActive(item)), '!pl-11']"
                        :aria-current="isAdminItemActive(item) ? 'page' : undefined"
                    >{{ item.label }}</Link>
                    <a
                        v-for="link in adminLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[itemClass(false), '!pl-11']"
                    >{{ link.label }}</a>
                </div>
            </div>
            <Link href="/profile" :class="itemClass(isActive('/profile'))"><Icon name="user" />Profile</Link>
            <button type="button" data-testid="logout" :class="itemClass(false)" @click="router.post('/logout')">
                <Icon name="logout" />Logout
            </button>
        </template>
        <Link v-else href="/login" :class="itemClass(isActive('/login'))"><Icon name="user" />Login</Link>
    </nav>
</template>

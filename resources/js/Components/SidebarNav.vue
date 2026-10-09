<script setup>
import {Link, usePage} from '@inertiajs/vue3';
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
                </div>
            </div>

            <Link href="/profile" :class="itemClass(isActive('/profile'))"><Icon name="user" />Profile</Link>
            <Link href="/logout" :class="itemClass(false)"><Icon name="logout" />Logout</Link>
        </template>
        <Link v-else href="/login" :class="itemClass(isActive('/login'))"><Icon name="user" />Login</Link>
    </nav>
</template>

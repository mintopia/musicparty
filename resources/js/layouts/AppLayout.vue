<script setup lang="ts">
import {Head, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import FlashMessages from '@/components/FlashMessages.vue';
import {useColorMode} from '@/composables/useColorMode';
import {buildNavigation, isActive} from '@/lib/navigation';
import {home} from '@/routes';
import {unimpersonate} from '@/routes/admin';
import {profile} from '@/routes/user';
import type {SharedProps} from '@/types';

defineProps<{title?: string}>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const site = computed(() => page.props.site);
const name = computed(() => page.props.instanceName ?? '');
const navigation = computed(() =>
    buildNavigation(page.props).map((item) => ({...item, active: isActive(item, page.url)})),
);

const menuOpen = ref(false);
const dropdownState = ref(new Map<string, boolean>());

function isOpen(key: string, active: boolean): boolean {
    return dropdownState.value.get(key) ?? active;
}

function toggleDropdown(key: string, active: boolean): void {
    const next = new Map(dropdownState.value);
    next.set(key, !isOpen(key, active));
    dropdownState.value = next;
}

useColorMode();
</script>

<template>
    <Head :title="title ?? name">
        <link v-if="site.favicon" rel="shortcut icon" :href="site.favicon" />
    </Head>
    <div class="page d-flex flex-column" style="min-height: 100vh">
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <button
                    class="navbar-toggler"
                    type="button"
                    aria-controls="sidebar-menu"
                    :aria-expanded="menuOpen"
                    aria-label="Toggle navigation"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark mt-1 px-2">
                    <a :href="home().url">
                        <template v-if="site.logoLight">
                            <img
                                :src="site.logoLight"
                                :alt="name"
                                style="max-height: 40px"
                                class="d-inline d-lg-none"
                            />
                            <img :src="site.logoLight" :alt="name" class="d-none d-lg-inline" />
                        </template>
                        <template v-else>{{ name }}</template>
                    </a>
                </h1>

                <div id="sidebar-menu" class="navbar-collapse collapse" :class="{show: menuOpen}">
                    <ul class="navbar-nav pt-lg-3">
                        <li
                            v-for="item in navigation"
                            :key="item.key"
                            class="nav-item"
                            :class="{dropdown: item.children, active: item.active}"
                        >
                            <template v-if="item.children">
                                <a
                                    class="nav-link dropdown-toggle"
                                    :class="{show: isOpen(item.key, item.active)}"
                                    href="#"
                                    role="button"
                                    :aria-expanded="isOpen(item.key, item.active)"
                                    @click.prevent="toggleDropdown(item.key, item.active)"
                                >
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="icon ti" :class="item.icon"></i>
                                    </span>
                                    <span class="nav-link-title">{{ item.title }}</span>
                                </a>
                                <div
                                    class="dropdown-menu"
                                    :class="{show: isOpen(item.key, item.active)}"
                                    data-bs-popper="static"
                                >
                                    <a
                                        v-for="child in item.children"
                                        :key="child.href"
                                        class="dropdown-item"
                                        :href="child.href"
                                    >
                                        {{ child.title }}
                                    </a>
                                </div>
                            </template>
                            <a v-else class="nav-link" :href="item.href">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="icon ti" :class="item.icon"></i>
                                </span>
                                <span class="nav-link-title">{{ item.title }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <div class="page-wrapper">
            <div v-if="user?.impersonating" class="navbar navbar-dark bg-dark-subtle">
                <div class="container-fluid">
                    <div class="navbar-text">You are impersonating {{ user.nickname }}</div>
                    <div class="nav-item">
                        <a :href="unimpersonate().url" class="btn btn-primary">Remove Impersonation</a>
                    </div>
                </div>
            </div>
            <header class="navbar d-print-none">
                <div class="container-xl">
                    <div class="navbar-nav flex-row">
                        <div class="d-flex py-2 px-0">
                            <ol class="breadcrumb" aria-label="breadcrumbs">
                                <slot name="breadcrumbs" />
                            </ol>
                        </div>
                    </div>

                    <div v-if="user" class="nav-item">
                        <a :href="profile().url" class="nav-link d-flex lh-1 text-reset p-0">
                            <span class="avatar avatar-sm" :style="{backgroundImage: `url('${user.avatar}')`}"></span>
                            <div class="d-none d-sm-block ps-2">
                                <div>{{ user.nickname }}</div>
                            </div>
                        </a>
                    </div>
                </div>
            </header>
            <slot name="header" />
            <div class="page-body">
                <div class="container-xl">
                    <FlashMessages />
                    <slot />
                </div>
            </div>
            <footer class="footer footer-transparent d-print-none mt-auto pb-2">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li v-if="site.terms" class="list-inline-item">
                                    <a :href="site.terms" target="_blank" class="link-secondary"
                                        >Terms and Conditions</a
                                    >
                                </li>
                                <li v-if="site.privacyPolicy" class="list-inline-item">
                                    <a :href="site.privacyPolicy" target="_blank" class="link-secondary"
                                        >Privacy Policy</a
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    Copyright &copy; {{ new Date().getFullYear() }}
                                    <a :href="home().url" class="link-secondary">{{ name }}</a
                                    >. All rights reserved
                                </li>
                                <li class="list-inline-item">
                                    Made with <i class="icon ti ti-heart-filled text-pink"></i> by
                                    <a class="link-muted" href="https://github.com/mintopia">Mintopia</a>
                                </li>
                                <li class="list-inline-item">
                                    <a
                                        href="https://github.com/mintopia/musicparty"
                                        class="link-muted link-underline-opacity-0"
                                    >
                                        <i class="icon ti ti-brand-github-filled"></i>GitHub
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

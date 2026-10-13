<script setup lang="ts">
import {Head, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import defaultCover from '@/../img/cover.jpg';
import FlashMessages from '@/components/FlashMessages.vue';
import {useColorMode} from '@/composables/useColorMode';
import {home} from '@/routes';
import type {SharedProps} from '@/types';

defineProps<{title?: string}>();

const page = usePage<SharedProps>();
const site = computed(() => page.props.site);
const name = computed(() => page.props.instanceName ?? '');
const cover = computed(() => site.value.coverImage ?? defaultCover);
const logo = computed(() => (page.props.theme.darkMode ? site.value.logoLight : site.value.logoDark));

useColorMode();
</script>

<template>
    <Head :title="title ?? name">
        <link v-if="site.favicon" rel="shortcut icon" :href="site.favicon" />
    </Head>
    <div class="d-flex flex-column login-page min-vh-100">
        <div class="row g-0 flex-fill">
            <div
                class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center"
            >
                <div class="container container-tight mt-auto px-lg-5">
                    <h1 class="mb-4 text-center">
                        <a :href="home().url" class="navbar-brand navbar-brand-autodark">
                            <img v-if="logo" :src="logo" :alt="name" />
                            <template v-else>{{ name }}</template>
                        </a>
                    </h1>
                    <FlashMessages centered />
                    <slot />
                </div>
                <footer class="footer footer-transparent mt-auto pt-3 pb-1">
                    <div class="container-xl">
                        <div class="row align-items-center flex-row-reverse">
                            <div class="col-5 text-end">
                                <ul class="list-unstyled mb-0">
                                    <li v-if="site.terms">
                                        <a :href="site.terms" target="_blank" class="link-secondary"
                                            >Terms and Conditions</a
                                        >
                                    </li>
                                    <li v-if="site.privacyPolicy">
                                        <a :href="site.privacyPolicy" target="_blank" class="link-secondary"
                                            >Privacy Policy</a
                                        >
                                    </li>
                                </ul>
                            </div>
                            <div class="col-7">
                                <ul class="list-inline list-inline-dots mb-0">
                                    <li class="list-inline-item">
                                        Copyright &copy; {{ new Date().getFullYear() }}
                                        <a :href="home().url" class="link-secondary">{{ name }}</a
                                        >.<br />
                                        All rights reserved.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
            <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
                <div class="bg-cover h-100 min-vh-100" :style="{backgroundImage: `url('${cover}')`}"></div>
            </div>
        </div>
    </div>
</template>

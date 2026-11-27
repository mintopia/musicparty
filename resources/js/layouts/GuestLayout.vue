<script setup lang="ts">
import {Head, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import defaultCover from '@/../img/cover.jpg';
import AppliedEntropyFooter from '@/components/AppliedEntropyFooter.vue';
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
    <div class="d-flex flex-column min-vh-100">
        <div class="row g-0 flex-fill">
            <div
                class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center"
            >
                <div class="container container-tight mt-auto px-lg-5">
                    <div class="mb-4 text-center">
                        <a id="site-brand" :href="home().url" class="navbar-brand navbar-brand-autodark">
                            <img v-if="logo" :src="logo" :alt="name" />
                            <span v-else class="site-brand-name">{{ name }}</span>
                        </a>
                    </div>
                    <FlashMessages centered />
                    <slot />
                </div>
                <footer class="footer footer-transparent ae-footer d-print-none mt-auto">
                    <div class="container-xl">
                        <AppliedEntropyFooter />
                    </div>
                </footer>
            </div>
            <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
                <div class="bg-cover h-100 min-vh-100" :style="{backgroundImage: `url('${cover}')`}"></div>
            </div>
        </div>
    </div>
</template>

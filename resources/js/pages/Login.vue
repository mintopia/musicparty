<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Icon from '../Components/Icon.vue';
import FlashAlerts from '../Components/ui/FlashAlerts.vue';
import coverUrl from '../../img/cover.jpg';
import {providerBrands, providerIcons} from '../lib/providerBrands.js';

defineOptions({layout: (h, page) => page});

const props = defineProps({
    providers: {type: Array, default: () => []},
});

const page = usePage();
const cover = computed(() => page.props.site?.cover_image_url ?? coverUrl);
const siteName = computed(() => page.props.site?.name || 'Music Party');
const logoOnLight = computed(() => page.props.site?.logo_dark_url ?? page.props.site?.logo_light_url ?? null);
const logoOnDark = computed(() => page.props.site?.logo_light_url ?? page.props.site?.logo_dark_url ?? null);

const items = computed(() =>
    (Array.isArray(props.providers) ? props.providers : []).map((provider) => ({
        ...provider,
        classes: providerBrands[provider.code] ?? 'bg-primary',
        icon: providerIcons.includes(provider.code) ? provider.code : 'login',
    })),
);

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Login" />
    <div class="flex min-h-screen w-full max-w-full flex-col overflow-x-hidden bg-background text-text md:flex-row">
        <section class="flex min-h-screen min-w-0 flex-1 flex-col border-t-2 border-primary md:min-h-0 md:w-[480px] md:max-w-[480px] md:flex-none md:basis-[480px]">
            <div class="flex flex-1 flex-col justify-center px-6 py-10 md:px-8">
                <h1 v-if="!logoOnLight && !logoOnDark" class="text-center text-2xl font-semibold md:text-xl">{{ siteName }}</h1>
                <h1 v-else class="flex justify-center">
                    <img :src="logoOnLight" :alt="siteName" data-testid="site-logo-light" class="max-h-24 max-w-full object-contain dark:hidden" />
                    <img :src="logoOnDark" :alt="siteName" data-testid="site-logo-dark" class="hidden max-h-24 max-w-full object-contain dark:block" />
                </h1>
                <p class="mt-6 text-center text-base md:mt-8 md:text-sm">Login to join the party!</p>

                <FlashAlerts class="mt-5" />

                <ul v-if="items.length" class="mt-5 flex flex-col gap-4 md:gap-4">
                    <li v-for="provider in items" :key="provider.code">
                        <a
                            :href="provider.url"
                            data-testid="provider-button"
                            class="flex min-h-14 w-full items-center justify-center gap-2 rounded px-4 text-base font-medium text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary md:min-h-10 md:text-sm"
                            :class="provider.classes"
                        >
                            <Icon :name="provider.icon" />
                            <span>Login with {{ provider.name }}</span>
                        </a>
                    </li>
                </ul>
                <p
                    v-else
                    data-testid="no-providers"
                    class="mt-5 rounded border border-border bg-surface px-4 py-3 text-center text-sm text-muted"
                >
                    No login providers are enabled right now. Please ask an admin to enable one.
                </p>
            </div>
            <footer class="px-4 pb-4 text-sm text-muted">
                <p>Copyright &copy; {{ year }} {{ siteName }}.</p>
                <p>All rights reserved.</p>
            </footer>
        </section>
        <img
            :src="cover"
            alt=""
            aria-hidden="true"
            class="hidden h-screen min-w-0 flex-1 basis-0 object-cover md:block"
        />
    </div>
</template>

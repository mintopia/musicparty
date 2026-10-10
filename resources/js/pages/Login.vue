<script setup>
import {Head} from '@inertiajs/vue3';
import {computed} from 'vue';
import Icon from '../Components/Icon.vue';
import coverUrl from '../../img/cover.jpg';

defineOptions({layout: (h, page) => page});

const props = defineProps({
    providers: {type: Array, default: () => []},
    error: {type: String, default: null},
});

const brands = {
    discord: 'bg-[#5865f2]',
    steam: 'bg-[#00adee]',
    twitch: 'bg-[#9146ff]',
};
const knownIcons = ['discord', 'steam', 'twitch'];

const items = computed(() =>
    (Array.isArray(props.providers) ? props.providers : []).map((provider) => ({
        ...provider,
        classes: brands[provider.code] ?? 'bg-primary',
        icon: knownIcons.includes(provider.code) ? provider.code : 'login',
    })),
);

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Login" />
    <div class="flex min-h-screen flex-col bg-background text-text md:flex-row">
        <section class="flex min-h-screen flex-1 flex-col border-t-2 border-primary md:min-h-0 md:w-[480px] md:max-w-[480px] md:flex-none md:basis-[480px]">
            <div class="flex flex-1 flex-col justify-center px-6 py-10 md:px-8">
                <h1 class="text-center text-2xl font-semibold md:text-xl">Music Party</h1>
                <p class="mt-6 text-center text-base md:mt-8 md:text-sm">Login to join the party!</p>

                <div
                    v-if="error"
                    role="alert"
                    data-testid="login-error"
                    class="mt-5 flex items-start gap-2 rounded border border-danger bg-surface px-4 py-3 text-sm text-danger"
                >
                    <Icon name="alert" />
                    <span>{{ error }}</span>
                </div>

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
                <p>Copyright &copy; {{ year }} Music Party.</p>
                <p>All rights reserved.</p>
            </footer>
        </section>
        <img
            :src="coverUrl"
            alt=""
            aria-hidden="true"
            class="hidden h-screen flex-1 object-cover md:block"
        />
    </div>
</template>

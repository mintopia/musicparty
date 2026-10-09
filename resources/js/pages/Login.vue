<script setup>
import {Head} from '@inertiajs/vue3';

defineProps({
    providers: {type: Array, default: () => []},
});
</script>

<template>
    <Head title="Login" />
    <div class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="text-xl font-semibold">Login</h1>
        <ul v-if="providers.length" class="mt-4 flex flex-col gap-2">
            <li v-for="provider in providers" :key="provider.code">
                <a
                    :href="`/login/${provider.code}`"
                    :data-provider="provider.code"
                    class="inline-block rounded border border-border bg-surface px-4 py-2 text-sm hover:text-primary"
                >Continue with {{ provider.name }}</a>
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-muted">No login providers are available.</p>
    </div>
</template>

<script setup lang="ts">
import {Head, usePage} from '@inertiajs/vue3';
import type {SharedProps} from '@/types';
import {useColorMode} from '@/composables/useColorMode';

defineProps<{title?: string}>();

const site = usePage<SharedProps>().props.site;

useColorMode();
</script>

<template>
    <Head :title="title">
        <link v-if="site.favicon" rel="shortcut icon" :href="site.favicon" />
    </Head>
    <slot />
</template>

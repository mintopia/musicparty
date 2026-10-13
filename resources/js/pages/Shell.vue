<script setup lang="ts">
import {Head, Link, usePage} from '@inertiajs/vue3';
import {shell} from '@/routes/inertia';
import type {SharedProps} from '@/types';

const page = usePage<SharedProps>();
</script>

<template>
    <Head title="Shell" />
    <h1>{{ page.props.instanceName }}</h1>
    <p v-if="page.props.auth.user">Signed in as {{ page.props.auth.user.nickname }}</p>
    <p v-else>Guest</p>
    <Link :href="shell().url">Reload</Link>
</template>

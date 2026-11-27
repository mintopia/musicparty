<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ThemeForm from '@/components/ThemeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    theme: {name: string; css: string; tokens: Record<string, string>; active: boolean; dark_mode: boolean};
    colourTokens: {token: string; label: string; default: string}[];
}>();

const form = useForm({
    name: props.theme.name,
    css: props.theme.css,
    active: props.theme.active,
    dark_mode: props.theme.dark_mode,
    tokens: {...props.theme.tokens},
});

function submit(): void {
    form.post('/admin/settings/themes');
}
</script>

<template>
    <AppLayout title="Add theme">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminSettingsCrumbs(), {label: 'Add theme'}]" />
        </template>
        <Head title="Add theme" />
        <form @submit.prevent="submit">
            <div class="mp-page-head">
                <div>
                    <h1>Add theme</h1>
                    <p>Start from the defaults and change only what you need.</p>
                </div>
                <div class="btn-list">
                    <Link href="/admin/settings" class="btn btn-link">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create theme</button>
                </div>
            </div>
            <ThemeForm :form="form" :readonly="false" :colour-tokens="colourTokens" />
        </form>
    </AppLayout>
</template>

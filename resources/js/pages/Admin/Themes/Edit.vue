<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminThemesCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ThemeForm from '@/components/ThemeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    theme: {
        id: number;
        name: string;
        css: string;
        tokens: Record<string, string>;
        active: boolean;
        dark_mode: boolean;
        readonly: boolean;
        updated_at: string | null;
    };
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
    form.patch(`/admin/themes/${props.theme.id}`);
}
</script>

<template>
    <AppLayout :title="`Edit ${theme.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminThemesCrumbs(), {label: `Edit ${theme.name}`}]" />
        </template>
        <Head :title="`Edit ${theme.name}`" />
        <form @submit.prevent="submit">
            <div class="mp-page-head">
                <div>
                    <h1>Edit {{ theme.name }}</h1>
                    <p>{{ theme.active ? `Active since ${theme.updated_at}.` : 'Not in use.' }}</p>
                </div>
                <div class="btn-list mp-btn-row">
                    <Link href="/admin/themes" class="btn btn-link">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save changes</button>
                </div>
            </div>
            <div v-if="theme.readonly" class="alert alert-info alert-important mb-4">
                It is not possible to edit this theme, you can only set it to be active and change dark mode.
            </div>
            <ThemeForm :form="form" :readonly="theme.readonly" :colour-tokens="colourTokens" />
        </form>
    </AppLayout>
</template>

<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import ThemeBreadcrumbs from '@/components/ThemeBreadcrumbs.vue';
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
    };
    colourTokens: {token: string; label: string; required: boolean}[];
}>();

const form = useForm({
    name: props.theme.name,
    css: props.theme.css,
    active: props.theme.active,
    dark_mode: props.theme.dark_mode,
    tokens: {...props.theme.tokens},
});

function submit(): void {
    form.patch(`/admin/settings/themes/${props.theme.id}`);
}
</script>

<template>
    <AppLayout :title="`Edit ${theme.name}`">
        <template #breadcrumbs>
            <ThemeBreadcrumbs>
                <li class="breadcrumb-item active">
                    <a :href="`/admin/settings/themes/${theme.id}/edit`">Edit Theme</a>
                </li>
            </ThemeBreadcrumbs>
        </template>
        <Head :title="`Edit ${theme.name}`" />
        <div class="page-header mt-0">
            <h1>Edit {{ theme.name }}</h1>
        </div>

        <div class="col-md-8 offset-md-2">
            <form class="card" @submit.prevent="submit">
                <div v-if="theme.readonly" class="alert alert-info alert-important m-4">
                    It is not possible to edit this theme, you can only set it to be active and change dark mode.
                </div>
                <ThemeForm :form="form" :readonly="theme.readonly" :colour-tokens="colourTokens" />
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link href="/admin/settings" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

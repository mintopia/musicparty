<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ThemeForm from '@/components/ThemeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    theme: {name: string; css: string; tokens: Record<string, string>; active: boolean; dark_mode: boolean};
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
    form.post('/admin/settings/themes');
}
</script>

<template>
    <AppLayout title="Create Theme">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminSettingsCrumbs(), {label: 'Create Theme'}]" />
        </template>
        <Head title="Create Theme" />
        <div class="page-header mt-0">
            <h1>Create Theme</h1>
        </div>

        <div class="col-md-8 offset-md-2">
            <form class="card" @submit.prevent="submit">
                <ThemeForm :form="form" :readonly="false" :colour-tokens="colourTokens" />
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

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed} from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    theme: {id: number; name: string; active: boolean; tokens: Record<string, string>};
}>();

const form = useForm({});

const thumbStyle = computed(() => ({
    '--t-nav': props.theme.tokens['nav-background'] ?? '#182433',
    '--t-bg': '#f6f8fb',
    '--t-surf': props.theme.tokens.surface ?? '#ffffff',
    '--t-pri': props.theme.tokens.primary ?? '#0054a6',
    '--t-text': props.theme.tokens.text ?? '#182433',
}));

function submit(): void {
    form.delete(`/admin/settings/themes/${props.theme.id}`);
}
</script>

<template>
    <AppLayout :title="`Delete ${theme.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminSettingsCrumbs(), {label: `Delete ${theme.name}`}]" />
        </template>
        <Head :title="`Delete ${theme.name}`" />
        <div class="mp-page-head">
            <div>
                <h1>Delete {{ theme.name }}</h1>
                <p>This cannot be undone.</p>
            </div>
        </div>

        <form class="card mx-auto" style="max-inline-size: 560px" @submit.prevent="submit">
            <div class="card-status-top bg-danger"></div>
            <div class="card-body text-center">
                <i class="icon mb-3 ti ti-alert-triangle icon-lg text-danger"></i>
                <p class="mt-3">
                    Delete the theme <strong>{{ theme.name }}</strong
                    >?
                </p>
                <div class="d-inline-flex mb-3">
                    <span class="mp-thumb" :style="thumbStyle" aria-hidden="true"><i></i><b></b></span>
                </div>
                <p v-if="theme.active" class="text-secondary" data-test="delete-active-note">
                    It is the active theme. The site goes back to the Default theme straight away, for everyone.
                </p>
            </div>
            <div class="card-footer d-flex">
                <Link href="/admin/settings" class="btn btn-link">Cancel</Link>
                <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                    Delete {{ theme.name }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminThemesCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {computed} from 'vue';
import {themeThumbStyle} from '@/lib/themeThumb';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    theme: {id: number; name: string; active: boolean; dark_mode: boolean; tokens: Record<string, string>};
}>();

const form = useForm({});

const thumbStyle = computed(() => themeThumbStyle(props.theme));

function submit(): void {
    form.delete(`/admin/themes/${props.theme.id}`);
}
</script>

<template>
    <AppLayout :title="`Delete ${theme.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminThemesCrumbs(), {label: `Delete ${theme.name}`}]" />
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
                <Link href="/admin/themes" class="btn btn-link">Cancel</Link>
                <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                    Delete {{ theme.name }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

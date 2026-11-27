<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageHead from '@/components/PageHead.vue';
import StatePill from '@/components/StatePill.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminThemesCrumbs} from '@/lib/breadcrumbs';
import {colourName, themeThumbStyle} from '@/lib/themeThumb';

interface ThemeRow {
    id: number;
    name: string;
    active: boolean;
    readonly: boolean;
    dark_mode: boolean;
    tokens: Record<string, string>;
    css: string;
    updated_at: string | null;
}

defineProps<{themes: ThemeRow[]}>();

function makeActive(theme: ThemeRow): void {
    router.patch(
        `/admin/themes/${theme.id}`,
        {
            name: theme.name,
            css: theme.css,
            dark_mode: theme.dark_mode,
            tokens: theme.tokens,
            active: true,
        },
        {preserveState: true, preserveScroll: true},
    );
}

function modeLabel(theme: ThemeRow): string {
    return theme.dark_mode ? 'Always dark' : 'Follows each user';
}

function subtitle(theme: ThemeRow): string {
    if (theme.readonly) {
        return 'Built in';
    }
    return `${colourName(theme.tokens.primary)}, ${theme.dark_mode ? 'dark' : 'light'}`;
}
</script>

<template>
    <AppLayout title="Themes">
        <template #breadcrumbs>
            <Breadcrumbs :items="adminThemesCrumbs()" />
        </template>
        <Head title="Themes" />
        <PageHead title="Themes" summary="Look and feel for the whole site.">
            <Link class="btn btn-primary" href="/admin/themes/create">
                <i class="icon ti ti-plus" aria-hidden="true"></i>
                Add theme
            </Link>
        </PageHead>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Theme</th>
                            <th>Status</th>
                            <th>Mode</th>
                            <th>Updated</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="theme in themes" :key="theme.id">
                            <td>
                                <div class="mp-cell">
                                    <span
                                        class="mp-thumb"
                                        :style="themeThumbStyle(theme)"
                                        role="img"
                                        :aria-label="`Preview of ${theme.name}`"
                                        ><i></i><b></b
                                    ></span>
                                    <div>
                                        <Link :href="`/admin/themes/${theme.id}/edit`" class="fw-medium">{{
                                            theme.name
                                        }}</Link>
                                        <span class="mp-sub">{{ subtitle(theme) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <StatePill v-if="theme.active" state="ok">Active</StatePill>
                                <span v-else class="text-secondary">Not in use</span>
                            </td>
                            <td>{{ modeLabel(theme) }}</td>
                            <td class="text-secondary">{{ theme.updated_at ?? 'Built in' }}</td>
                            <td>
                                <div class="btn-list flex-nowrap justify-content-end">
                                    <button
                                        v-if="!theme.active"
                                        type="button"
                                        class="btn btn-sm"
                                        data-test="make-active"
                                        @click="makeActive(theme)"
                                    >
                                        Make active
                                    </button>
                                    <Link
                                        :href="`/admin/themes/${theme.id}/edit`"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        <i
                                            class="icon ti"
                                            :class="theme.readonly ? 'ti-eye' : 'ti-edit'"
                                            aria-hidden="true"
                                        ></i>
                                        {{ theme.readonly ? 'View' : 'Edit' }}
                                    </Link>
                                    <Link
                                        v-if="!theme.readonly"
                                        :href="`/admin/themes/${theme.id}/delete`"
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        <i class="icon ti ti-trash" aria-hidden="true"></i>
                                        Delete
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="themes.length === 0">
                            <td colspan="5" class="text-center p-4">
                                <p class="mb-0">There are no themes</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-secondary small">Only one theme is active, and everyone sees it.</div>
        </div>
    </AppLayout>
</template>

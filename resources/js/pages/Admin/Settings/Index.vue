<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import {colourName, themeThumbStyle} from '@/lib/themeThumb';
import {computed, ref} from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface SettingField {
    code: string;
    name: string;
    description: string | null;
    type: 'string' | 'boolean';
    required: boolean;
    value: string | boolean;
}

const props = defineProps<{
    settings: SettingField[];
    themes: {
        id: number;
        name: string;
        active: boolean;
        readonly: boolean;
        dark_mode: boolean;
        tokens: Record<string, string>;
        css: string;
        updated_at: string | null;
    }[];
    socialProviders: {
        id: number;
        code: string;
        name: string;
        supports_auth: boolean;
        enabled: boolean;
        auth_enabled: boolean;
        login_return_url: string;
        linked_account_url: string;
    }[];
}>();

const form = useForm<Record<string, string | boolean>>(
    Object.fromEntries(props.settings.map((setting) => [setting.code, setting.value])),
);

function submit(): void {
    form.patch('/admin/settings');
}

function makeActive(theme: {
    id: number;
    name: string;
    css: string;
    dark_mode: boolean;
    tokens: Record<string, string>;
}): void {
    router.patch(
        `/admin/settings/themes/${theme.id}`,
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

function modeLabel(theme: {dark_mode: boolean}): string {
    return theme.dark_mode ? 'Always dark' : 'Follows each user';
}

function subtitle(theme: {readonly: boolean; dark_mode: boolean; tokens: Record<string, string>}): string {
    if (theme.readonly) {
        return 'Built in';
    }
    return `${colourName(theme.tokens.primary)}, ${theme.dark_mode ? 'dark' : 'light'}`;
}

type TabKey = 'general' | 'providers' | 'themes';

const heads: Record<TabKey, {title: string; blurb: string}> = {
    general: {title: 'General', blurb: 'Site-wide options.'},
    providers: {title: 'Sign-in providers', blurb: 'Choose how people sign in.'},
    themes: {title: 'Themes', blurb: 'Look and feel for the whole site.'},
};

const tabs = computed(() => [
    ...(props.settings.length > 0 ? [{key: 'general' as TabKey, label: 'General'}] : []),
    {key: 'providers' as TabKey, label: 'Sign-in providers'},
    {key: 'themes' as TabKey, label: 'Themes'},
]);

const hash = typeof window === 'undefined' ? '' : window.location.hash.slice(1);
const requested = tabs.value.find((tab) => tab.key === hash);
const activeTab = ref<TabKey>(requested?.key ?? tabs.value[0].key);

function selectTab(key: TabKey): void {
    activeTab.value = key;
    window.history.replaceState(null, '', `#${key}`);
}
</script>

<template>
    <AppLayout title="Settings">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminCrumbs(), {label: 'Settings'}]" />
        </template>
        <Head title="Settings" />
        <div class="mp-narrow">
            <div class="mp-page-head">
                <div>
                    <h1>{{ heads[activeTab].title }}</h1>
                    <p>{{ heads[activeTab].blurb }}</p>
                </div>
            </div>
            <nav class="underline-tabs mb-4" aria-label="Settings" data-test="settings-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="underline-tab"
                    :class="{active: tab.key === activeTab}"
                    :aria-current="tab.key === activeTab ? 'page' : undefined"
                    :data-test="`tab-${tab.key}`"
                    @click="selectTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <div class="row">
                <div v-if="settings.length > 0 && activeTab === 'general'" class="col-12">
                    <form class="card mb-4" @submit.prevent="submit">
                        <div class="card-body">
                            <div v-for="setting in settings" :key="setting.code" class="mb-3">
                                <template v-if="setting.type === 'string'">
                                    <label
                                        class="form-label"
                                        :class="{required: setting.required}"
                                        :for="setting.code"
                                        >{{ setting.name }}</label
                                    >
                                    <div>
                                        <input
                                            :id="setting.code"
                                            v-model="form[setting.code]"
                                            type="text"
                                            :name="setting.code"
                                            class="form-control"
                                            :class="{'is-invalid': form.errors[setting.code]}"
                                        />
                                        <small v-if="setting.description" class="form-hint">{{
                                            setting.description
                                        }}</small>
                                        <p v-if="form.errors[setting.code]" class="invalid-feedback">
                                            {{ form.errors[setting.code] }}
                                        </p>
                                    </div>
                                </template>
                                <template v-else>
                                    <label class="form-check form-switch">
                                        <input
                                            v-model="form[setting.code]"
                                            type="checkbox"
                                            class="form-check-input"
                                            :name="setting.code"
                                        />
                                        {{ setting.name }}
                                    </label>
                                    <small v-if="setting.description" class="form-hint">{{
                                        setting.description
                                    }}</small>
                                </template>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                                    Save
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div v-if="activeTab === 'themes'" class="col-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title">Themes</h2>
                            <Link class="btn btn-primary ms-auto" href="/admin/settings/themes/create">
                                <i class="icon ti ti-plus"></i>
                                Add theme
                            </Link>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th class="w-1">Status</th>
                                        <th>Mode</th>
                                        <th>Updated</th>
                                        <th></th>
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
                                                    <Link
                                                        :href="`/admin/settings/themes/${theme.id}/edit`"
                                                        class="fw-medium"
                                                        >{{ theme.name }}</Link
                                                    >
                                                    <span class="mp-sub">{{ subtitle(theme) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span v-if="theme.active" class="mp-state is-ok">Active</span>
                                            <span v-else class="text-secondary">Not in use</span>
                                        </td>
                                        <td>{{ modeLabel(theme) }}</td>
                                        <td class="text-secondary">{{ theme.updated_at ?? 'Built in' }}</td>
                                        <td>
                                            <div class="btn-list justify-content-end flex-nowrap">
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
                                                    :href="`/admin/settings/themes/${theme.id}/edit`"
                                                    class="btn btn-sm"
                                                >
                                                    <i v-if="!theme.readonly" class="icon ti ti-edit"></i>
                                                    {{ theme.readonly ? 'View' : 'Edit' }}
                                                </Link>
                                                <Link
                                                    v-if="!theme.readonly"
                                                    :href="`/admin/settings/themes/${theme.id}/delete`"
                                                    class="btn btn-sm btn-outline-danger"
                                                >
                                                    <i class="icon ti ti-trash"></i>
                                                    Delete
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="themes.length === 0">
                                        <td colspan="5" class="text-center p-4">
                                            <p>There are no themes</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div v-if="activeTab === 'providers'" class="col col-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title">Social Providers</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th class="w-1">Supports Auth</th>
                                        <th class="w-1">Enabled</th>
                                        <th class="w-1">Auth Enabled</th>
                                        <th>Return URLs</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="provider in socialProviders" :key="provider.id">
                                        <td>
                                            {{ provider.name }}
                                            <br />
                                            <span class="text-muted">{{ provider.code }}</span>
                                        </td>
                                        <td>
                                            <span
                                                class="status"
                                                :class="provider.supports_auth ? 'status-green' : 'status-muted'"
                                                >{{ provider.supports_auth ? 'Yes' : 'No' }}</span
                                            >
                                        </td>
                                        <td>
                                            <span
                                                class="status"
                                                :class="provider.enabled ? 'status-green' : 'status-red'"
                                                >{{ provider.enabled ? 'Yes' : 'No' }}</span
                                            >
                                        </td>
                                        <td>
                                            <span v-if="provider.auth_enabled" class="status status-green">Yes</span>
                                            <span v-else-if="provider.supports_auth" class="status status-red">No</span>
                                            <span v-else class="status status-muted">N/A</span>
                                        </td>
                                        <td>
                                            <span class="user-select-all">{{ provider.login_return_url }}</span
                                            ><br />
                                            <span class="user-select-all">{{ provider.linked_account_url }}</span>
                                        </td>
                                        <td>
                                            <div class="btn-list justify-content-end">
                                                <Link
                                                    :href="`/admin/settings/socialproviders/${provider.id}/edit`"
                                                    class="btn btn-sm"
                                                >
                                                    <i class="icon ti ti-edit"></i>
                                                    Edit
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="socialProviders.length === 0">
                                        <td colspan="6" class="text-center p-4">
                                            <p>There are no social providers</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

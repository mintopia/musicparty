<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
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
    themes: {id: number; name: string; active: boolean; readonly: boolean}[];
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
</script>

<template>
    <AppLayout title="Settings">
        <template #breadcrumbs>
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
            <li class="breadcrumb-item active"><a href="/admin/settings">Settings</a></li>
        </template>
        <Head title="Settings" />
        <div class="page-header mt-0">
            <h1>Settings</h1>
        </div>

        <div class="row">
            <div v-if="settings.length > 0" class="col-12 col-lg-6">
                <form class="card mb-4" @submit.prevent="submit">
                    <div class="card-body">
                        <div v-for="setting in settings" :key="setting.code" class="mb-3">
                            <template v-if="setting.type === 'string'">
                                <label class="form-label" :class="{required: setting.required}" :for="setting.code">{{
                                    setting.name
                                }}</label>
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
                                <small v-if="setting.description" class="form-hint">{{ setting.description }}</small>
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
            <div class="col-12 col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Themes</h3>
                        <Link class="btn btn-primary ms-auto" href="/admin/settings/themes/create">
                            <i class="icon ti ti-pencil-plus"></i>
                            Add Theme
                        </Link>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="w-1">Active</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="theme in themes" :key="theme.id">
                                    <td>{{ theme.name }}</td>
                                    <td>
                                        <span class="status" :class="theme.active ? 'status-green' : 'status-muted'">
                                            {{ theme.active ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-list justify-content-end">
                                            <Link
                                                :href="`/admin/settings/themes/${theme.id}/edit`"
                                                class="btn btn-outline-primary"
                                            >
                                                <i class="icon ti ti-edit"></i>
                                                Edit
                                            </Link>
                                            <Link
                                                v-if="!theme.readonly"
                                                :href="`/admin/settings/themes/${theme.id}/delete`"
                                                class="btn btn-outline-danger"
                                            >
                                                <i class="icon ti ti-trash"></i>
                                                Delete
                                            </Link>
                                            <a v-else class="btn btn-outline-danger disabled">
                                                <i class="icon ti ti-trash"></i>
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="themes.length === 0">
                                    <td colspan="3" class="text-center p-4">
                                        <p>There are no themes</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Social Providers</h3>
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
                                                class="btn btn-outline-primary"
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
    </AppLayout>
</template>

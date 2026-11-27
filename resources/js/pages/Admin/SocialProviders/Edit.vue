<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    provider: {
        id: number;
        name: string;
        can_be_renamed: boolean;
        supports_auth: boolean;
        enabled: boolean;
        auth_enabled: boolean;
    };
    settings: {code: string; name: string; description: string | null; required: boolean; value: string}[];
}>();

const form = useForm<Record<string, string | boolean>>({
    ...(props.provider.can_be_renamed ? {name: props.provider.name} : {}),
    ...Object.fromEntries(props.settings.map((setting) => [setting.code, setting.value])),
    enabled: props.provider.enabled,
    ...(props.provider.supports_auth ? {auth_enabled: props.provider.auth_enabled} : {}),
});

function submit(): void {
    form.patch(`/admin/settings/socialproviders/${props.provider.id}`);
}
</script>

<template>
    <AppLayout :title="`Edit ${provider.name}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminSettingsCrumbs(), {label: `Edit ${provider.name}`}]" />
        </template>
        <Head :title="`Edit ${provider.name}`" />
        <div class="page-header mt-0">
            <h1>Edit {{ provider.name }}</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div v-if="provider.can_be_renamed" class="mb-3">
                        <label class="form-label required" for="name">Name</label>
                        <div>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                name="name"
                                class="form-control"
                                :class="{'is-invalid': form.errors.name}"
                                placeholder="Name"
                            />
                            <small class="form-hint">The name of the provider. </small>
                            <p v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div v-for="setting in settings" :key="setting.code" class="mb-3">
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
                            <small v-if="setting.description" class="form-hint">{{ setting.description }}</small>
                            <p v-if="form.errors[setting.code]" class="invalid-feedback">
                                {{ form.errors[setting.code] }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input v-model="form.enabled" type="checkbox" class="form-check-input" name="enabled" />
                            Enabled
                        </label>
                    </div>
                    <div v-if="provider.supports_auth" class="mb-3">
                        <label class="form-check form-switch">
                            <input
                                v-model="form.auth_enabled"
                                type="checkbox"
                                class="form-check-input"
                                name="auth_enabled"
                            />
                            Authentication Enabled
                        </label>
                    </div>
                </div>
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

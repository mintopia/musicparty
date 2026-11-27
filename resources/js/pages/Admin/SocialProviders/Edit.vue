<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CopyButton from '@/components/CopyButton.vue';
import PageHead from '@/components/PageHead.vue';
import SettingRow from '@/components/SettingRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {adminSettingsCrumbs} from '@/lib/breadcrumbs';

const props = defineProps<{
    provider: {
        id: number;
        name: string;
        can_be_renamed: boolean;
        supports_auth: boolean;
        enabled: boolean;
        auth_enabled: boolean;
        login_return_url?: string;
        linked_account_url?: string;
    };
    settings: {code: string; name: string; description: string | null; required: boolean; value: string}[];
}>();

const form = useForm<Record<string, string | boolean>>({
    ...(props.provider.can_be_renamed ? {name: props.provider.name} : {}),
    ...Object.fromEntries(props.settings.map((setting) => [setting.code, setting.value])),
    enabled: props.provider.enabled,
    ...(props.provider.supports_auth ? {auth_enabled: props.provider.auth_enabled} : {}),
});

const returnUrls = [
    {
        id: 'login-return-url',
        label: 'Sign-in return URL',
        hint: `Add this to ${props.provider.name} as an allowed redirect.`,
        value: props.provider.login_return_url,
        action: 'Copy sign-in return URL',
        show: props.provider.supports_auth,
    },
    {
        id: 'linked-account-url',
        label: 'Linking return URL',
        hint: `Used when a signed-in user links ${props.provider.name} from their Profile.`,
        value: props.provider.linked_account_url,
        action: 'Copy linking return URL',
        show: true,
    },
].filter((row) => row.show && row.value);

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
        <div class="mp-narrow mp-touch">
            <PageHead
                :title="`Edit ${provider.name}`"
                :summary="
                    provider.supports_auth
                        ? `${provider.name} can be used to log in and to link an account.`
                        : `${provider.name} can be linked to an account but not used to log in.`
                "
            />
            <form class="card" @submit.prevent="submit">
                <div class="card-body pt-2 pb-0">
                    <SettingRow v-if="provider.can_be_renamed" label="Name" for="name" :error="form.errors.name">
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            name="name"
                            class="form-control"
                            :class="{'is-invalid': form.errors.name}"
                            placeholder="Name"
                        />
                    </SettingRow>

                    <SettingRow
                        v-for="setting in settings"
                        :key="setting.code"
                        :label="setting.name"
                        :for="setting.code"
                        :hint="setting.description ?? undefined"
                        :error="form.errors[setting.code]"
                    >
                        <input
                            :id="setting.code"
                            v-model="form[setting.code]"
                            type="text"
                            :name="setting.code"
                            class="form-control"
                            :class="{'is-invalid': form.errors[setting.code]}"
                        />
                    </SettingRow>

                    <SettingRow label="Enabled" for="enabled" :hint="`Lets users link a ${provider.name} account.`">
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                id="enabled"
                                v-model="form.enabled"
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                name="enabled"
                            />
                        </span>
                    </SettingRow>
                    <SettingRow
                        v-if="provider.supports_auth"
                        label="Sign-in enabled"
                        for="auth_enabled"
                        :hint="`Shows ${provider.name} on the login page.`"
                    >
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                id="auth_enabled"
                                v-model="form.auth_enabled"
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                name="auth_enabled"
                            />
                        </span>
                    </SettingRow>

                    <SettingRow
                        v-for="row in returnUrls"
                        :key="row.id"
                        stacked
                        :label="row.label"
                        :for="row.id"
                        :hint="row.hint"
                    >
                        <div class="input-group">
                            <input :id="row.id" type="text" class="form-control" readonly :value="row.value" />
                            <CopyButton :value="row.value ?? ''" :label="row.action" text="Copy" />
                        </div>
                    </SettingRow>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <Link href="/admin/settings" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                            Save changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
import SettingRow from '@/components/SettingRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    user: {
        id: number;
        nickname: string;
        terms: boolean;
        first_login_completed: boolean;
        suspended: boolean;
    };
    roles: {code: string; name: string}[];
    currentRoles: string[];
}>();

const form = useForm({
    nickname: props.user.nickname,
    roles: [...props.currentRoles],
    terms: props.user.terms,
    first_login: props.user.first_login_completed,
    suspended: props.user.suspended,
});

function submit(): void {
    form.patch(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <AppLayout :title="`Edit ${user.nickname}`">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...adminUserCrumbs(user), {label: 'Edit'}]" />
        </template>
        <Head :title="`Edit ${user.nickname}`" />
        <div class="mp-narrow mp-touch">
            <PageHead
                :title="`Edit ${user.nickname}`"
                :summary="`User ${user.id}. Changes apply the next time they load a page.`"
            />
            <form class="card mx-auto" style="max-inline-size: 660px" @submit.prevent="submit">
                <div class="card-body pt-2 pb-0">
                    <SettingRow
                        label="Nickname"
                        for="nickname"
                        hint="Shown on Requests, votes and the Members list."
                        :error="form.errors.nickname"
                    >
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            name="nickname"
                            class="form-control"
                            :class="{'is-invalid': form.errors.nickname}"
                            placeholder="Nickname"
                        />
                    </SettingRow>
                    <SettingRow label="Roles" hint="Admins can open everything under Admin.">
                        <label
                            v-for="role in roles"
                            :key="role.code"
                            class="form-check mb-0 d-flex align-items-center gap-2"
                        >
                            <input
                                v-model="form.roles"
                                class="form-check-input m-0"
                                type="checkbox"
                                :value="role.code"
                            />
                            <span class="form-check-label">{{ role.name }}</span>
                        </label>
                    </SettingRow>
                    <SettingRow
                        label="Accepted Terms and Conditions"
                        for="terms"
                        hint="Records whether they have agreed to the terms and conditions."
                    >
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                id="terms"
                                v-model="form.terms"
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                name="terms"
                            />
                        </span>
                    </SettingRow>
                    <SettingRow
                        label="Completed signup"
                        for="first_login"
                        hint="Off sends them back to the signup form on next login."
                    >
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                id="first_login"
                                v-model="form.first_login"
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                name="first_login"
                            />
                        </span>
                    </SettingRow>
                    <SettingRow label="Suspended" for="suspended" hint="Suspended users cannot log in.">
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                id="suspended"
                                v-model="form.suspended"
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                name="suspended"
                            />
                        </span>
                    </SettingRow>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                            Save changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

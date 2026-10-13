<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import UserBreadcrumbs from '@/components/UserBreadcrumbs.vue';
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
            <UserBreadcrumbs :user="user">
                <li class="breadcrumb-item active"><a :href="`/admin/users/${user.id}/edit`">Edit</a></li>
            </UserBreadcrumbs>
        </template>
        <Head :title="`Edit ${user.nickname}`" />
        <div class="page-header mt-0">
            <h1>Edit {{ user.nickname }}</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="nickname">Nickname</label>
                        <div>
                            <input
                                id="nickname"
                                v-model="form.nickname"
                                type="text"
                                name="nickname"
                                class="form-control"
                                :class="{'is-invalid': form.errors.nickname}"
                                placeholder="Nickname"
                            />
                            <small class="form-hint">The user's nickname</small>
                            <p v-if="form.errors.nickname" class="invalid-feedback">{{ form.errors.nickname }}</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <div class="form-label">Roles</div>
                        <div>
                            <label v-for="role in roles" :key="role.code" class="form-check">
                                <input
                                    v-model="form.roles"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="role.code"
                                />
                                <span class="form-check-label">{{ role.name }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input v-model="form.terms" type="checkbox" class="form-check-input" name="terms" />
                            Accept Terms and Conditions
                        </label>
                        <label class="form-check form-switch">
                            <input
                                v-model="form.first_login"
                                type="checkbox"
                                class="form-check-input"
                                name="first_login"
                            />
                            Completed Signup
                        </label>
                        <label class="form-check form-switch">
                            <input v-model="form.suspended" type="checkbox" class="form-check-input" name="suspended" />
                            Suspended
                        </label>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

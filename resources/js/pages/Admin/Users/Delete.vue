<script setup lang="ts">
import {Head, Link, useForm} from '@inertiajs/vue3';
import UserBreadcrumbs from '@/components/UserBreadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{user: {id: number; nickname: string}}>();

const form = useForm({confirm: ''});

function submit(): void {
    form.delete(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <AppLayout :title="`Delete ${user.nickname}`">
        <template #breadcrumbs>
            <UserBreadcrumbs :user="user">
                <li class="breadcrumb-item active"><a :href="`/admin/users/${user.id}/delete`">Delete</a></li>
            </UserBreadcrumbs>
        </template>
        <Head :title="`Delete ${user.nickname}`" />
        <div class="page-header mt-0">
            <h1>Delete {{ user.nickname }}</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon mb-4 ti ti-alert-triangle icon-lg text-danger"></i>
                    <p class="mt-4">
                        Are you sure you want to delete <strong>{{ user.nickname }}</strong
                        >?
                    </p>
                    <p>To continue, please type 'delete' in the box below.</p>
                    <div class="mb-3 col-md-6 offset-md-3">
                        <div>
                            <input
                                v-model="form.confirm"
                                type="text"
                                name="confirm"
                                class="form-control"
                                :class="{'is-invalid': form.errors.confirm}"
                                placeholder="delete"
                            />
                            <p v-if="form.errors.confirm" class="invalid-feedback">{{ form.errors.confirm }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

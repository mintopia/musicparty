<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {profile} from '@/routes/user';
import {update as updateProfile} from '@/routes/user/profile';

const props = defineProps<{user: {nickname: string}}>();

const form = useForm({nickname: props.user.nickname ?? ''});

function submit(): void {
    form.patch(updateProfile().url);
}
</script>

<template>
    <AppLayout title="Edit Profile">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Profile', href: profile().url}, {label: 'Edit Profile'}]" />
        </template>
        <Head title="Edit Profile" />
        <div class="page-header mt-0">
            <h1>Edit Profile</h1>
        </div>

        <div class="col-md-6 offset-md-3">
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Nickname</label>
                        <div>
                            <input
                                v-model="form.nickname"
                                type="text"
                                name="nickname"
                                class="form-control"
                                :class="{'is-invalid': form.errors.nickname}"
                                placeholder="Nickname"
                            />
                            <small class="form-hint">Your preferred nickname</small>
                            <p v-if="form.errors.nickname" class="invalid-feedback">{{ form.errors.nickname }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <div class="d-flex">
                        <Link :href="profile().url" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

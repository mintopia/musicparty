<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {homeCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
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
    <AppLayout title="Edit profile">
        <template #breadcrumbs>
            <Breadcrumbs :items="[...homeCrumbs(), {label: 'Profile', href: profile().url}, {label: 'Edit profile'}]" />
        </template>
        <Head title="Edit profile" />
        <div class="mp-narrow">
            <PageHead title="Edit profile" summary="How you appear in parties." />

            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div>
                        <label class="form-label required" for="nickname">Nickname</label>
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            name="nickname"
                            class="form-control"
                            :class="{'is-invalid': form.errors.nickname}"
                        />
                        <small class="form-hint">Shown next to your Requests.</small>
                        <p v-if="form.errors.nickname" class="invalid-feedback">{{ form.errors.nickname }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <Link :href="profile().url" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                            Save changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

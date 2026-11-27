<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs} from '@/lib/breadcrumbs';
import {Head, Link, useForm} from '@inertiajs/vue3';
import PageHead from '@/components/PageHead.vue';
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
            <Breadcrumbs :items="[...adminUserCrumbs(user), {label: 'Delete'}]" />
        </template>
        <Head :title="`Delete ${user.nickname}`" />
        <div class="mp-narrow mp-touch">
            <PageHead :title="`Delete ${user.nickname}`" />
            <form class="card mx-auto" style="max-inline-size: 36rem" @submit.prevent="submit">
                <div class="card-status-top bg-danger"></div>
                <div class="card-body text-center">
                    <i class="icon ti ti-alert-triangle icon-lg text-danger" aria-hidden="true"></i>
                    <p class="mt-4">
                        Delete <strong>{{ user.nickname }}</strong
                        >? This cannot be undone.
                    </p>
                    <label class="d-block mb-2" for="confirm"> To continue, type <strong>delete</strong> below. </label>
                    <div class="mx-auto" style="max-inline-size: 14rem">
                        <input
                            id="confirm"
                            v-model="form.confirm"
                            type="text"
                            name="confirm"
                            class="form-control text-center"
                            :class="{'is-invalid': form.errors.confirm}"
                            placeholder="delete"
                            autocomplete="off"
                        />
                        <p v-if="form.errors.confirm" class="invalid-feedback">{{ form.errors.confirm }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <Link :href="`/admin/users/${user.id}`" class="btn btn-link">Cancel</Link>
                        <button type="submit" class="btn btn-danger ms-auto" :disabled="form.processing">
                            Delete {{ user.nickname }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

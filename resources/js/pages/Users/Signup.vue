<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {logout} from '@/routes';
import {signup} from '@/routes/login';

const props = defineProps<{
    user: {nickname: string; avatar: string};
    terms: string | null;
    privacy: string | null;
}>();

const form = useForm<{nickname: string; terms: string | null}>({nickname: props.user.nickname ?? '', terms: null});

function submit(): void {
    form.put(signup().url);
}
</script>

<template>
    <GuestLayout title="Create Account">
        <Head title="Create Account" />
        <h2 class="h3 text-center mb-3">Create Account</h2>

        <div class="mb-3 text-center">
            <span class="avatar avatar-xl rounded" :style="{backgroundImage: `url('${user.avatar}')`}"></span>
        </div>

        <form @submit.prevent="submit">
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
            <div v-if="terms || privacy" class="mb-5">
                <div>
                    <label class="form-check" :class="{'is-invalid': form.errors.terms}">
                        <input v-model="form.terms" class="form-check-input" name="terms" value="1" type="checkbox" />
                        <span class="form-check-label required">
                            I agree to the
                            <template v-if="terms">
                                <a :href="terms">Terms and Conditions</a>
                                <template v-if="privacy"> and </template>
                            </template>
                            <a v-if="privacy" :href="privacy">Privacy Policy</a>
                        </span>
                    </label>
                    <p v-if="form.errors.terms" class="invalid-feedback">{{ form.errors.terms }}</p>
                </div>
            </div>
            <div class="d-flex">
                <a :href="logout().url" class="btn btn-link">Cancel</a>
                <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">Continue</button>
            </div>
        </form>
    </GuestLayout>
</template>

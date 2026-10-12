<script setup>
import {computed} from 'vue';
import {Head, router, useForm} from '@inertiajs/vue3';

defineOptions({layout: (h, page) => page});

const props = defineProps({
    nickname: {type: String, default: ''},
    avatarUrl: {type: String, default: null},
    termsUrl: {type: String, default: null},
    privacyUrl: {type: String, default: null},
    submitUrl: {type: String, default: '/signup'},
});

const form = useForm({
    nickname: props.nickname,
    terms: false,
});

const needsConsent = computed(() => Boolean(props.termsUrl || props.privacyUrl));
const cancel = () => router.post('/logout');
const submit = () => form.post(props.submitUrl);
</script>

<template>
    <Head title="Sign up" />
    <div class="flex min-h-screen flex-col bg-background text-text md:items-center md:justify-center">
        <main class="flex w-full flex-1 flex-col justify-center border-t-2 border-primary px-6 py-10 md:max-w-md md:flex-none md:border-t-0 md:py-0">
            <div class="md:rounded md:border md:border-border md:bg-surface md:p-8">
                <h1 class="text-center text-2xl font-semibold md:text-xl">Create Account</h1>
                <div v-if="avatarUrl" class="mt-4 flex justify-center">
                    <img :src="avatarUrl" alt="" data-testid="avatar" class="h-20 w-20 rounded object-cover" />
                </div>

                <form class="mt-6 flex flex-col gap-5" novalidate @submit.prevent="submit">
                    <div>
                        <label for="nickname" class="mb-1 block text-sm font-medium">Nickname</label>
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            name="nickname"
                            autocomplete="nickname"
                            maxlength="32"
                            class="min-h-12 w-full rounded border border-border bg-background px-3 text-base text-text focus:border-primary focus:outline-none md:min-h-10 md:text-sm"
                            :class="{'border-danger': form.errors.nickname}"
                        />
                        <p class="mt-1 text-xs text-muted">Your preferred nickname</p>
                        <p v-if="form.errors.nickname" data-testid="nickname-error" class="mt-1 text-sm text-danger">
                            {{ form.errors.nickname }}
                        </p>
                    </div>

                    <div v-if="needsConsent">
                        <label class="flex min-h-12 items-start gap-3 text-sm md:min-h-0">
                            <input
                                v-model="form.terms"
                                type="checkbox"
                                name="terms"
                                class="mt-0.5 h-6 w-6 shrink-0 accent-primary md:h-4 md:w-4"
                            />
                            <span>
                                I agree to the
                                <a v-if="termsUrl" :href="termsUrl" target="_blank" rel="noopener" class="text-primary underline">Terms and Conditions</a>
                                <template v-if="termsUrl && privacyUrl"> and </template>
                                <a v-if="privacyUrl" :href="privacyUrl" target="_blank" rel="noopener" class="text-primary underline">Privacy Policy</a>
                            </span>
                        </label>
                        <p v-if="form.errors.terms" data-testid="terms-error" class="mt-1 text-sm text-danger">
                            {{ form.errors.terms }}
                        </p>
                    </div>

                    <div class="flex items-center">
                        <button type="button" data-testid="cancel" class="min-h-12 px-2 text-sm text-primary underline md:min-h-10" @click="cancel">Cancel</button>
                        <button
                            type="submit"
                            :disabled="form.processing || (needsConsent && !form.terms)"
                            class="ml-auto min-h-14 rounded bg-primary px-6 text-base font-medium text-white transition hover:brightness-110 disabled:opacity-50 md:min-h-10 md:text-sm"
                        >
                            Continue
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>

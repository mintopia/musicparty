<script setup>
import {Head, useForm} from '@inertiajs/vue3';

defineOptions({layout: (h, page) => page});

const props = defineProps({
    nickname: {type: String, default: ''},
    termsUrl: {type: String, default: null},
    privacyUrl: {type: String, default: null},
    submitUrl: {type: String, default: '/signup'},
});

const form = useForm({
    nickname: props.nickname,
    terms: false,
});

const submit = () => form.post(props.submitUrl);
</script>

<template>
    <Head title="Sign up" />
    <div class="flex min-h-screen flex-col bg-background text-text md:items-center md:justify-center">
        <main class="flex w-full flex-1 flex-col justify-center border-t-2 border-primary px-6 py-10 md:max-w-md md:flex-none md:border-t-0 md:py-0">
            <div class="md:rounded md:border md:border-border md:bg-surface md:p-8">
                <h1 class="text-center text-2xl font-semibold md:text-xl">Music Party</h1>
                <p class="mt-4 text-center text-base md:text-sm">Pick a nickname to finish signing up.</p>

                <form class="mt-6 flex flex-col gap-5" novalidate @submit.prevent="submit">
                    <div>
                        <label for="nickname" class="mb-1 block text-sm font-medium">Nickname</label>
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            name="nickname"
                            autocomplete="nickname"
                            maxlength="64"
                            class="min-h-12 w-full rounded border border-border bg-background px-3 text-base text-text focus:border-primary focus:outline-none md:min-h-10 md:text-sm"
                            :class="{'border-danger': form.errors.nickname}"
                        />
                        <p v-if="form.errors.nickname" data-testid="nickname-error" class="mt-1 text-sm text-danger">
                            {{ form.errors.nickname }}
                        </p>
                    </div>

                    <div v-if="termsUrl">
                        <label class="flex min-h-12 items-start gap-3 text-sm md:min-h-0">
                            <input
                                v-model="form.terms"
                                type="checkbox"
                                name="terms"
                                class="mt-0.5 h-6 w-6 shrink-0 accent-primary md:h-4 md:w-4"
                            />
                            <span>
                                I agree to the
                                <a :href="termsUrl" target="_blank" rel="noopener" class="text-primary underline">Terms of Service</a>
                                <template v-if="privacyUrl">
                                    and
                                    <a :href="privacyUrl" target="_blank" rel="noopener" class="text-primary underline">Privacy Policy</a>
                                </template>.
                            </span>
                        </label>
                        <p v-if="form.errors.terms" data-testid="terms-error" class="mt-1 text-sm text-danger">
                            {{ form.errors.terms }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || (termsUrl && !form.terms)"
                        class="min-h-14 w-full rounded bg-primary px-4 text-base font-medium text-white transition hover:brightness-110 disabled:opacity-50 md:min-h-10 md:text-sm"
                    >
                        Continue
                    </button>
                </form>
            </div>
        </main>
    </div>
</template>

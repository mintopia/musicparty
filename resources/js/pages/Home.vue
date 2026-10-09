<script setup>
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Icon from '../Components/Icon.vue';

defineProps({canCreateParty: {type: Boolean, default: false}});

const user = computed(() => usePage().props.auth.user);

const form = useForm({code: ''});
const submit = () => form.post('/parties/join');
const normalise = () => {
    form.code = form.code.toUpperCase().replace(/[^A-Z]/g, '');
};
</script>

<template>
    <Head title="Home" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-8 md:items-end">
        <div
            v-if="user"
            class="w-full rounded border border-border bg-surface px-5 pb-6 pt-5 text-center md:w-[280px]"
        >
            <img :src="user.avatarUrl" alt="" class="mx-auto h-20 w-20 rounded" />
            <div class="mt-3 text-sm font-medium">{{ user.name }}</div>
            <div v-if="user.email" class="mt-1 text-sm text-muted">{{ user.email }}</div>
        </div>

        <section
            data-testid="join-party"
            class="w-full rounded border border-border bg-surface px-5 py-5 md:w-[280px]"
        >
            <h2 class="text-sm font-semibold">Join a party</h2>
            <form v-if="user" class="mt-3 flex flex-col gap-3" @submit.prevent="submit">
                <label for="join-code" class="sr-only">Party code</label>
                <input
                    id="join-code"
                    v-model="form.code"
                    type="text"
                    maxlength="4"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="ABCD"
                    :aria-invalid="form.errors.code ? 'true' : undefined"
                    class="min-h-11 w-full rounded border border-border bg-background px-3 text-center text-base uppercase tracking-widest text-text focus-visible:outline-2 focus-visible:outline-primary"
                    @input="normalise"
                />
                <p v-if="form.errors.code" role="alert" class="text-sm text-danger">{{ form.errors.code }}</p>
                <button
                    type="submit"
                    :disabled="form.processing || form.code.length !== 4"
                    class="min-h-11 rounded bg-primary px-4 text-sm font-medium text-white hover:brightness-110 disabled:opacity-50"
                >
                    Join
                </button>
            </form>
            <p v-else class="mt-3 text-sm text-muted">
                <Link href="/login" class="text-primary hover:underline">Log in to join a party</Link>
            </p>
            <Link
                v-if="user && canCreateParty"
                href="/parties/create"
                class="mt-3 flex min-h-11 items-center justify-center gap-2 rounded border border-border text-sm font-medium hover:text-primary"
            >
                <Icon name="plus" />Create a party
            </Link>
        </section>
    </div>
</template>

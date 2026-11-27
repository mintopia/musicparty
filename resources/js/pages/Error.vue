<script setup lang="ts">
import {router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {home} from '@/routes';
import {show} from '@/routes/parties';
import type {SharedProps} from '@/types';

const props = defineProps<{
    status: number;
    title: string;
    message: string;
    partyNotFound: boolean;
}>();

const PARTY_CODE = /^[A-Z]{4}$/;

const page = usePage<SharedProps>();
const layout = computed(() => (page.props.auth?.user ? AppLayout : GuestLayout));
const codeEntryOpen = ref(false);
const code = ref('');
const codeError = ref<string | null>(null);
const showCodeField = computed(() => props.partyNotFound || codeEntryOpen.value);
const isDanger = computed(() => props.status >= 500);

function normaliseCode(): void {
    code.value = code.value.toUpperCase();
    codeError.value = null;
}

function join(): void {
    const value = code.value.trim().toUpperCase();
    if (!PARTY_CODE.test(value)) {
        codeError.value = 'Party codes are four letters, no digits.';
        return;
    }
    router.visit(show(value).url);
}

function reload(): void {
    window.location.reload();
}
</script>

<template>
    <component :is="layout" :title="`${status} ${title}`">
        <template #breadcrumbs>
            <Breadcrumbs
                :items="[{label: 'Home', href: home().url}, {label: partyNotFound ? 'Party not found' : 'Error'}]"
            />
        </template>
        <div class="mp-error mp-stage" :class="{'is-danger': isDanger}" :data-status="status" data-test="error-page">
            <span v-if="partyNotFound" class="mp-error-icon" aria-hidden="true"><i class="icon ti ti-search"></i></span>
            <span v-else class="mp-error-code" aria-hidden="true">{{ status }}</span>
            <h1>{{ title }}</h1>
            <p>{{ message }}</p>
            <form
                v-if="showCodeField"
                class="mp-error-code-form"
                novalidate
                data-test="party-code-form"
                @submit.prevent="join"
            >
                <label class="form-label" for="error-party-code">Party code</label>
                <div class="mp-error-code-row">
                    <input
                        id="error-party-code"
                        v-model="code"
                        class="form-control mp-error-code-input"
                        :class="{'is-invalid': codeError}"
                        type="text"
                        maxlength="4"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="ABCD"
                        :aria-invalid="codeError ? 'true' : undefined"
                        aria-describedby="error-party-code-hint"
                        :autofocus="partyNotFound"
                        @input="normaliseCode"
                    />
                    <button type="submit" class="btn btn-primary">Join</button>
                </div>
                <div
                    id="error-party-code-hint"
                    class="form-hint"
                    :class="{'text-danger': codeError}"
                    :role="codeError ? 'alert' : undefined"
                    data-test="party-code-hint"
                >
                    {{ codeError ?? 'Four letters, no digits.' }}
                </div>
            </form>
            <div v-else class="btn-list justify-content-center">
                <button v-if="status >= 500" type="button" class="btn btn-primary" @click="reload">
                    <i class="icon ti ti-refresh"></i>Try again
                </button>
                <a :href="home().url" class="btn" :class="status >= 500 ? 'btn-outline-secondary' : 'btn-primary'">
                    <i class="icon ti ti-home"></i>Go to Home
                </a>
                <button
                    v-if="status === 404"
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="codeEntryOpen = true"
                >
                    <i class="icon ti ti-door-enter"></i>Enter party code
                </button>
            </div>
            <div v-if="showCodeField" class="btn-list justify-content-center mt-3">
                <a :href="home().url" class="btn btn-outline-secondary"><i class="icon ti ti-home"></i>Go to Home</a>
            </div>
        </div>
    </component>
</template>

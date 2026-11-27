<script setup lang="ts">
import {router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {home, login} from '@/routes';
import {show} from '@/routes/parties';
import type {SharedProps} from '@/types';

interface ErrorAction {
    label: string;
    href: string;
    icon: string;
}

const props = withDefaults(
    defineProps<{
        status: number;
        title: string;
        message: string;
        partyNotFound: boolean;
        typedCode?: string | null;
        icon?: string | null;
        actions?: ErrorAction[];
    }>(),
    {typedCode: null, icon: null, actions: () => []},
);

const PARTY_CODE = /^[A-Z]{4}$/;
const NUMBER_WORDS = ['none', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

function codeProblem(value: string): string | null {
    if (PARTY_CODE.test(value)) {
        return null;
    }
    if (/[^A-Z]/.test(value)) {
        return 'Party codes are four letters, no digits.';
    }
    if (value.length === 0) {
        return 'Party codes are four letters.';
    }
    return `Party codes are four letters. You typed ${NUMBER_WORDS[value.length] ?? value.length}.`;
}

const inertiaPage = usePage<SharedProps>();
const isGuest = computed(() => !inertiaPage.props.auth?.user);
const layout = computed(() => (isGuest.value ? GuestLayout : AppLayout));
const codeEntryOpen = ref(false);
const code = ref(props.partyNotFound ? (props.typedCode ?? '') : '');
const codeError = ref<string | null>(props.partyNotFound ? codeProblem(code.value) : null);
const showCodeField = computed(() => props.partyNotFound || codeEntryOpen.value);
const isDanger = computed(() => props.status >= 500);
const iconClass = computed(() => props.icon ?? (props.partyNotFound ? 'ti-search' : null));
const body = computed(() =>
    isGuest.value && props.status === 404 && !props.partyNotFound && props.actions.length === 0
        ? 'The link may be old. Enter a party code or login.'
        : props.message,
);

function normaliseCode(): void {
    code.value = code.value.toUpperCase();
    codeError.value = null;
}

function join(): void {
    const value = code.value.trim().toUpperCase();
    const problem = codeProblem(value);
    if (problem) {
        codeError.value = problem;
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
        <div
            class="mp-error"
            :class="{'mp-stage': !isGuest, 'py-0': isGuest, 'is-danger': isDanger}"
            :data-status="status"
            data-test="error-page"
        >
            <span v-if="iconClass" class="mp-error-icon" aria-hidden="true"
                ><i class="icon ti" :class="iconClass"></i
            ></span>
            <span v-else class="mp-error-code" aria-hidden="true">{{ status }}</span>
            <h1 :class="{h3: isGuest}">{{ title }}</h1>
            <p>{{ body }}</p>
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
                    class="mt-2"
                    :class="codeError ? 'invalid-feedback d-block' : 'form-hint'"
                    :role="codeError ? 'alert' : undefined"
                    data-test="party-code-hint"
                >
                    {{ codeError ?? 'Four letters, no digits.' }}
                </div>
            </form>
            <div v-else-if="actions.length > 0" class="btn-list justify-content-center" data-test="error-actions">
                <a
                    v-for="(action, index) in actions"
                    :key="action.href"
                    :href="action.href"
                    class="btn"
                    :class="index === 0 ? 'btn-primary' : 'btn-outline-secondary'"
                >
                    <i class="icon ti" :class="action.icon"></i>{{ action.label }}
                </a>
            </div>
            <div v-else-if="isGuest && status === 404" class="btn-list justify-content-center">
                <button type="button" class="btn btn-primary" @click="codeEntryOpen = true">
                    <i class="icon ti ti-door-enter"></i>Enter party code
                </button>
                <a :href="login().url" class="btn btn-outline-secondary">
                    <i class="icon ti ti-brand-discord"></i>Login
                </a>
            </div>
            <div v-else class="btn-list justify-content-center">
                <button v-if="status >= 500" type="button" class="btn btn-primary" @click="reload">
                    <i class="icon ti ti-rotate-clockwise"></i>Try again
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
            <div v-if="showCodeField && !partyNotFound" class="btn-list justify-content-center mt-3">
                <a :href="home().url" class="btn btn-outline-secondary"><i class="icon ti ti-home"></i>Go to Home</a>
            </div>
        </div>
    </component>
</template>

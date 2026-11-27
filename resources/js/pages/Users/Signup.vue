<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import JoinSteps from '@/components/JoinSteps.vue';
import type {PartyInvite} from '@/components/PartyInviteCard.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {logout} from '@/routes';
import {signup} from '@/routes/login';

const props = defineProps<{
    user: {nickname: string; avatar: string};
    party?: PartyInvite | null;
    terms: string | null;
    privacy: string | null;
}>();

const form = useForm<{nickname: string; terms: string | null}>({nickname: props.user.nickname ?? '', terms: null});

function submit(): void {
    form.put(signup().url);
}
</script>

<template>
    <GuestLayout :title="party ? 'Pick a nickname' : 'Create Account'">
        <Head :title="party ? 'Pick a nickname' : 'Create Account'" />
        <JoinSteps v-if="party" :current="2" />
        <template v-if="party">
            <h1 class="h3 text-center mb-1">Pick a nickname</h1>
            <p class="text-secondary text-center">
                You're joining <b>{{ party.name }}</b> ({{ party.code }}).
            </p>
        </template>
        <h2 v-else class="h3 text-center mb-3">Create Account</h2>

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
                    <small class="form-hint">{{
                        party ? 'Shown next to your Requests.' : 'Your preferred nickname'
                    }}</small>
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
                <button type="submit" class="btn btn-primary ms-auto" :disabled="form.processing">
                    {{ party ? 'Continue to the party' : 'Continue' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>

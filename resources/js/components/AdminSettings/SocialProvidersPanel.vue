<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import StatePill from '@/components/StatePill.vue';
import {Link} from '@inertiajs/vue3';
import type {SocialProviderRow} from '@/lib/adminSettings';

defineProps<{socialProviders: SocialProviderRow[]}>();

const providerIcons: Record<string, string> = {
    discord: 'ti-brand-discord',
    spotify: 'ti-brand-spotify',
    spotifysearch: 'ti-brand-spotify',
    steam: 'ti-brand-steam',
    twitch: 'ti-brand-twitch',
};

function providerStatus(provider: SocialProviderRow): {
    state: 'ok' | 'live' | 'idle' | 'warn';
    label: string;
    note: string | null;
} {
    if (!provider.enabled) {
        return {state: 'idle', label: 'Off', note: provider.supports_auth ? null : 'Search only, cannot log in'};
    }
    if (provider.needs_secret) {
        return {state: 'warn', label: 'Needs a secret', note: 'On, but the client secret is empty'};
    }
    if (provider.auth_enabled) {
        return {state: 'ok', label: 'Sign-in on', note: null};
    }
    return {
        state: 'live',
        label: 'Linking only',
        note: provider.supports_auth ? 'Sign-in is off' : 'Used to play and search, not to log in',
    };
}
</script>

<template>
    <div id="panel-providers" role="tabpanel" aria-labelledby="tab-providers">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Sign-in providers</h2>
                <span class="ms-auto text-secondary d-none d-md-inline"
                    >Copy a return URL into the provider's developer console.</span
                >
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>Return URLs</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="provider in socialProviders" :key="provider.id">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <i
                                        class="icon ti text-secondary"
                                        :class="providerIcons[provider.code] ?? 'ti-key'"
                                        aria-hidden="true"
                                    ></i>
                                    <div>
                                        <div class="fw-medium">{{ provider.name }}</div>
                                        <div class="text-secondary small">{{ provider.code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <StatePill :state="providerStatus(provider).state">{{
                                    providerStatus(provider).label
                                }}</StatePill>
                                <div v-if="providerStatus(provider).note" class="text-secondary small mt-1">
                                    {{ providerStatus(provider).note }}
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <CopyButton
                                        :value="provider.login_return_url"
                                        :label="`Copy ${provider.name} sign-in URL`"
                                        text="Sign-in URL"
                                    />
                                    <CopyButton
                                        :value="provider.linked_account_url"
                                        :label="`Copy ${provider.name} linking URL`"
                                        text="Linking URL"
                                    />
                                </div>
                            </td>
                            <td>
                                <Link
                                    :href="`/admin/settings/socialproviders/${provider.id}/edit`"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    <i class="icon ti ti-edit" aria-hidden="true"></i>
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="socialProviders.length === 0">
                            <td colspan="4" class="text-center p-4">
                                <p class="mb-0">There are no social providers</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

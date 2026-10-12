<script setup>
import {usePartyPresence} from '../../composables/usePartyPresence';
import {Head, router, useForm} from '@inertiajs/vue3';
import {onBeforeUnmount, ref} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    tokens: {type: Array, default: () => []},
    issuedToken: {type: Object, default: null},
});

usePartyPresence(props.party.code);

const shown = ref(props.issuedToken);
const copied = ref(false);
const form = useForm({name: ''});

const clearShown = () => {
    shown.value = null;
    copied.value = false;
};

const stopListening = router.on('navigate', (event) => {
    shown.value = event.detail.page.props.issuedToken ?? null;
    copied.value = false;
});
onBeforeUnmount(() => {
    stopListening?.();
    clearShown();
});

const issue = () => {
    form.post(`/parties/${props.party.code}/player-tokens`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const copy = async () => {
    await navigator.clipboard.writeText(shown.value.token);
    copied.value = true;
};

const revoke = (token) => {
    if (window.confirm(`Revoke the Player Token "${token.name}"? Any Player using it will be disconnected.`)) {
        router.delete(`/parties/${props.party.code}/player-tokens/${token.id}`, {preserveScroll: true});
    }
};

const when = (value, fallback) => (value ? new Date(value).toLocaleString() : fallback);
</script>

<template>
    <Head :title="`${party.name} player tokens`" />
    <h1 class="text-xl font-semibold">Player Tokens</h1>

    <div v-if="shown" data-testid="issued-token" role="status" class="mt-4 rounded border border-border bg-surface p-3 text-sm">
        <p>Token for <strong>{{ shown.name }}</strong>. Copy it now; it will not be shown again.</p>
        <div class="mt-2 flex items-center gap-2">
            <code data-token class="break-all font-mono">{{ shown.token }}</code>
            <button type="button" data-action="copy" class="rounded border border-border px-2 py-1" @click="copy">
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </div>
    </div>

    <form class="mt-4 flex items-start gap-2" @submit.prevent="issue">
        <div>
            <label for="token-name" class="block text-sm">Label</label>
            <input
                id="token-name"
                v-model="form.name"
                type="text"
                maxlength="64"
                class="mt-1 rounded border border-border bg-surface px-3 py-2 text-sm"
                :aria-invalid="form.errors.name ? 'true' : undefined"
            />
            <p v-if="form.errors.name" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
        </div>
        <button type="submit" :disabled="form.processing" class="mt-6 rounded bg-primary px-3 py-2 text-sm text-white">Issue token</button>
    </form>

    <table class="mt-6 w-full text-left text-sm">
        <thead>
            <tr>
                <th>Label</th>
                <th>Last used</th>
                <th>Expires</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="token in tokens" :key="token.id" data-token-row>
                <td>{{ token.name }}</td>
                <td>{{ when(token.last_used_at, 'Never') }}</td>
                <td>{{ when(token.expires_at, 'Never') }}</td>
                <td>
                    <button type="button" data-action="revoke" class="text-danger" @click="revoke(token)">Revoke</button>
                </td>
            </tr>
            <tr v-if="!tokens.length">
                <td colspan="4">No Player Tokens issued.</td>
            </tr>
        </tbody>
    </table>
</template>

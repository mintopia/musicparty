<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

const props = defineProps({
    tokens: {type: Object, required: true},
    abilities: {type: Array, default: () => []},
    issued: {type: Object, default: null},
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const form = useForm({name: '', abilities: []});

const issue = () => {
    form.post('/admin/tokens', {preserveScroll: true, onSuccess: () => form.reset()});
};
const revoke = (token) => {
    router.delete(`/admin/tokens/${token.id}`, {preserveScroll: true});
};
const lastUsed = (token) => (token.last_used_at ? new Date(token.last_used_at).toLocaleString() : 'Never');
</script>

<template>
    <Head title="Integration tokens" />
    <h1 class="text-xl font-semibold">Integration tokens</h1>

    <div v-if="props.issued" role="status" data-testid="issued-token" class="mt-4 rounded border border-border bg-surface p-3 text-sm">
        <p>Token "{{ props.issued.name }}" created. Copy it now; it will not be shown again.</p>
        <code class="mt-2 block break-all">{{ props.issued.value }}</code>
    </div>

    <form class="mt-4 max-w-sm space-y-3" @submit.prevent="issue">
        <div>
            <label for="token-name" class="block text-sm">Name</label>
            <input
                id="token-name"
                v-model="form.name"
                type="text"
                class="mt-1 w-full rounded border border-border bg-surface px-3 py-2 text-sm"
            />
            <p v-if="errors.name" role="alert" class="mt-1 text-sm">{{ errors.name }}</p>
        </div>
        <fieldset>
            <legend class="text-sm">Abilities</legend>
            <label v-for="ability in abilities" :key="ability" class="mr-3 inline-flex items-center gap-1 text-sm">
                <input v-model="form.abilities" type="checkbox" :value="ability" :data-ability="ability" />
                {{ ability }}
            </label>
            <p v-if="errors.abilities" role="alert" class="mt-1 text-sm">{{ errors.abilities }}</p>
        </fieldset>
        <button type="submit" data-action="issue" class="rounded border border-border px-2 py-1 text-sm">Issue token</button>
    </form>

    <table class="mt-6 w-full text-left text-sm">
        <caption class="sr-only">Integration tokens</caption>
        <thead>
            <tr>
                <th scope="col" class="py-2">Name</th>
                <th scope="col" class="py-2">Abilities</th>
                <th scope="col" class="py-2">Last used</th>
                <th scope="col" class="py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="token in tokens.data" :key="token.id" :data-testid="`token-${token.id}`" class="border-t border-border">
                <td class="py-2">{{ token.name }}</td>
                <td class="py-2">{{ token.abilities.join(', ') }}</td>
                <td class="py-2">{{ lastUsed(token) }}</td>
                <td class="py-2">
                    <span v-if="token.revoked">Revoked</span>
                    <button
                        v-else
                        type="button"
                        data-action="revoke"
                        class="rounded border border-border px-2 py-1"
                        @click="revoke(token)"
                    >Revoke</button>
                </td>
            </tr>
            <tr v-if="!tokens.data.length">
                <td colspan="4" class="py-4 text-muted">No integration tokens.</td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Alert from '../../../Components/ui/Alert.vue';
import Button from '../../../Components/ui/Button.vue';
import Card from '../../../Components/ui/Card.vue';
import DataTable from '../../../Components/ui/DataTable.vue';
import FormField from '../../../Components/ui/FormField.vue';
import StatusPill from '../../../Components/ui/StatusPill.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    tokens: {type: Object, required: true},
    abilities: {type: Array, default: () => []},
    issued: {type: Object, default: null},
});

usePageMeta({title: 'Integration tokens', crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Tokens'}]});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const form = useForm({name: '', abilities: []});

const columns = [
    {key: 'name', label: 'Name'},
    {key: 'abilities', label: 'Abilities'},
    {key: 'last_used_at', label: 'Last used'},
    {key: 'actions', label: 'Actions'},
];

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

    <Alert v-if="props.issued" variant="success" data-testid="issued-token" class="mb-4">
        <p>Token "{{ props.issued.name }}" created. Copy it now; it will not be shown again.</p>
        <code class="mt-2 block break-all">{{ props.issued.value }}</code>
    </Alert>

    <div class="grid gap-4 lg:grid-cols-3">
        <form class="lg:col-span-1" @submit.prevent="issue">
            <Card>
                <template #header>Issue token</template>
                <FormField label="Name" for="token-name" :error="errors.name">
                    <input
                        id="token-name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                    />
                </FormField>
                <fieldset>
                    <legend class="mb-1 text-sm font-medium">Abilities</legend>
                    <label v-for="ability in abilities" :key="ability" class="mr-3 inline-flex items-center gap-1 text-sm">
                        <input v-model="form.abilities" type="checkbox" :value="ability" :data-ability="ability" />
                        {{ ability }}
                    </label>
                    <p v-if="errors.abilities" role="alert" class="mt-1 text-sm text-danger">{{ errors.abilities }}</p>
                </fieldset>
                <template #footer><Button type="submit" data-action="issue">Issue token</Button></template>
            </Card>
        </form>

        <div class="lg:col-span-2">
            <Card flush>
                <template #header>Tokens</template>
                <DataTable
                    :columns="columns"
                    :rows="tokens.data"
                    caption="Integration tokens"
                    empty-text="No integration tokens."
                    :row-attrs="(token) => ({'data-testid': `token-${token.id}`})"
                >
                    <template #row="{row: token}">
                        <td class="px-5 py-3">{{ token.name }}</td>
                        <td class="px-5 py-3">{{ token.abilities.join(', ') }}</td>
                        <td class="px-5 py-3">{{ lastUsed(token) }}</td>
                        <td class="px-5 py-3">
                            <StatusPill v-if="token.revoked" variant="red">Revoked</StatusPill>
                            <Button v-else variant="outline-danger" data-action="revoke" @click="revoke(token)">Revoke</Button>
                        </td>
                    </template>
                </DataTable>
            </Card>
        </div>
    </div>
</template>

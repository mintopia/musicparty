<script setup>
import {Head, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Icon from '../../../Components/Icon.vue';
import Alert from '../../../Components/ui/Alert.vue';
import Button from '../../../Components/ui/Button.vue';
import ButtonLink from '../../../Components/ui/ButtonLink.vue';
import Card from '../../../Components/ui/Card.vue';
import FormField from '../../../Components/ui/FormField.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    user: {type: Object, required: true},
    parties: {type: Array, default: () => []},
});

const page = usePage();
const error = computed(() => page.props.errors?.admin ?? null);

usePageMeta(() => ({
    title: `Delete ${props.user.nickname}`,
    crumbs: [
        {label: 'Home', href: '/'},
        {label: 'Admin', href: '/admin'},
        {label: 'Users', href: '/admin/users'},
        {label: props.user.nickname, href: `/admin/users/${props.user.id}`},
        {label: 'Delete'},
    ],
}));

const form = useForm({confirm: ''});
const confirmed = computed(() => form.confirm === 'delete');
const submit = () => form.delete(`/admin/users/${props.user.id}`);
</script>

<template>
    <Head :title="`Delete ${user.nickname}`" />
    <div v-if="error" class="mb-4"><Alert variant="danger">{{ error }}</Alert></div>
    <form @submit.prevent="submit">
        <Card status="danger">
            <template #header>Delete {{ user.nickname }}</template>
            <p class="mb-4 text-sm">This permanently deletes the user, their linked accounts and everything they own. It cannot be undone.</p>
            <div v-if="parties.length" class="mb-4" data-testid="cascade-parties">
                <p class="mb-2 text-sm font-medium">These parties will be deleted with them:</p>
                <ul class="list-disc pl-6 text-sm">
                    <li v-for="party in parties" :key="party.id">{{ party.name }} <span class="text-muted">({{ party.code }})</span></li>
                </ul>
            </div>
            <FormField label="Type delete to confirm" for="confirm" required :error="form.errors.confirm">
                <input
                    id="confirm"
                    v-model="form.confirm"
                    type="text"
                    autocomplete="off"
                    class="w-full max-w-sm rounded border border-border bg-background px-3 py-2 text-sm"
                />
            </FormField>
            <template #footer>
                <ButtonLink :href="`/admin/users/${user.id}`" variant="secondary" data-action="cancel">Cancel</ButtonLink>
                <Button type="submit" variant="danger" :disabled="!confirmed || form.processing" data-action="confirm-delete">
                    <template #icon><Icon name="trash" /></template>
                    Delete user
                </Button>
            </template>
        </Card>
    </form>
</template>

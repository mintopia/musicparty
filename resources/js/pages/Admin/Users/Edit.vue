<script setup>
import {Head, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Alert from '../../../Components/ui/Alert.vue';
import Button from '../../../Components/ui/Button.vue';
import ButtonLink from '../../../Components/ui/ButtonLink.vue';
import Card from '../../../Components/ui/Card.vue';
import FormField from '../../../Components/ui/FormField.vue';
import Toggle from '../../../Components/ui/Toggle.vue';
import {usePageMeta} from '../../../composables/usePageMeta.js';

defineOptions({layout: AdminLayout});

const props = defineProps({
    user: {type: Object, required: true},
    roleOptions: {type: Array, required: true},
});

const page = usePage();
const isSelf = computed(() => page.props.auth?.user?.id === props.user.id);

usePageMeta(() => ({
    title: `Edit ${props.user.nickname}`,
    crumbs: [
        {label: 'Home', href: '/'},
        {label: 'Admin', href: '/admin'},
        {label: 'Users', href: '/admin/users'},
        {label: props.user.nickname, href: `/admin/users/${props.user.id}`},
        {label: 'Edit'},
    ],
}));

const form = useForm({
    nickname: props.user.nickname,
    roles: props.user.roles.map((role) => role.code),
    agreed_terms: props.user.agreed_terms,
    completed_signup: props.user.completed_signup,
    suspended: props.user.suspended,
});

const toggleRole = (code) => {
    form.roles = form.roles.includes(code) ? form.roles.filter((role) => role !== code) : [...form.roles, code];
};

const submit = () => form.patch(`/admin/users/${props.user.id}`);
</script>

<template>
    <Head :title="`Edit ${user.nickname}`" />
    <div v-if="form.errors.admin" class="mb-4"><Alert variant="danger">{{ form.errors.admin }}</Alert></div>
    <form @submit.prevent="submit">
        <Card>
            <FormField label="Nickname" for="nickname" required :error="form.errors.nickname">
                <input
                    id="nickname"
                    v-model="form.nickname"
                    type="text"
                    required
                    class="w-full rounded border border-border bg-background px-3 py-2 text-sm"
                />
            </FormField>

            <fieldset class="mb-4">
                <legend class="mb-1 text-sm font-medium">Roles</legend>
                <label v-for="role in roleOptions" :key="role.code" class="mr-4 inline-flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        :data-role="role.code"
                        :checked="form.roles.includes(role.code)"
                        @change="toggleRole(role.code)"
                    />
                    {{ role.label }}
                </label>
                <p v-if="form.errors.roles" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.roles }}</p>
            </fieldset>

            <div class="flex flex-col gap-3">
                <Toggle v-model="form.agreed_terms" label="Accept terms and conditions" />
                <Toggle v-model="form.completed_signup" label="Completed signup" />
                <Toggle v-model="form.suspended" label="Suspended" :disabled="isSelf" />
            </div>
            <template #footer>
                <ButtonLink :href="`/admin/users/${user.id}`" variant="secondary" data-action="cancel">Cancel</ButtonLink>
                <Button type="submit" :disabled="form.processing" data-action="save">Save</Button>
            </template>
        </Card>
    </form>
</template>

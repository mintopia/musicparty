<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, reactive} from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({layout: AdminLayout});

const props = defineProps({
    providers: {type: Array, required: true},
});

const page = usePage();
const error = computed(() => page.props.errors?.enabled ?? null);

const form = reactive(Object.fromEntries(props.providers.map((provider) => [
    provider.code,
    Object.fromEntries(provider.fields.map((field) => [field.code, field.value ?? ''])),
])));

const save = (provider, enabled = null) => {
    const data = {settings: form[provider.code]};
    if (enabled !== null) {
        data.enabled = enabled;
    }
    router.put(`/admin/providers/${provider.code}`, data, {preserveScroll: true});
};
</script>

<template>
    <Head title="Login providers" />
    <h1 class="text-xl font-semibold">Login providers</h1>

    <div v-if="error" role="alert" class="mt-4 rounded border border-border bg-surface p-3 text-sm">
        {{ error }}
    </div>

    <section
        v-for="provider in providers"
        :key="provider.code"
        :data-testid="`provider-${provider.code}`"
        class="mt-4 rounded border border-border p-4"
    >
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ provider.name }}</h2>
            <span class="text-sm text-muted">{{ provider.enabled ? 'Enabled' : 'Disabled' }}</span>
        </div>
        <form class="mt-3 flex flex-col gap-3" @submit.prevent="save(provider)">
            <div v-for="field in provider.fields" :key="field.code">
                <label :for="`${provider.code}-${field.code}`" class="block text-sm">{{ field.name }}</label>
                <input
                    :id="`${provider.code}-${field.code}`"
                    v-model="form[provider.code][field.code]"
                    :type="field.secret ? 'password' : 'text'"
                    autocomplete="off"
                    class="mt-1 w-full max-w-sm rounded border border-border bg-surface px-3 py-2 text-sm"
                />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded border border-border px-3 py-1 text-sm">Save credentials</button>
                <button
                    type="button"
                    data-action="toggle"
                    class="rounded border border-border px-3 py-1 text-sm"
                    @click="save(provider, !provider.enabled)"
                >{{ provider.enabled ? 'Disable' : 'Enable' }}</button>
            </div>
        </form>
    </section>
</template>

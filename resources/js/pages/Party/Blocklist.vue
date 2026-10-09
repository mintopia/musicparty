<script setup>
import {Head, Link, router, useForm, usePage} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    entries: {type: Array, required: true},
    matchTypes: {type: Array, required: true},
    abilities: {type: Object, required: true},
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.successMessage ?? null);

const base = `/parties/${props.party.code}/blocklist`;
const typeFor = (value) => props.matchTypes.find((type) => type.value === value) ?? null;
const typeLabel = (value) => typeFor(value)?.label ?? value;

const form = useForm({
    match_type: props.matchTypes[0]?.value ?? '',
    value: '',
    is_regex: false,
    is_enabled: true,
    notes: '',
});

const supportsRegex = computed(() => typeFor(form.match_type)?.supportsRegex ?? false);

watch(supportsRegex, (supported) => {
    if (!supported) {
        form.is_regex = false;
    }
});

const submit = () => {
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => form.reset('value', 'notes', 'is_regex'),
    });
};

const busy = ref(false);
const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
};

const toggle = (entry) => {
    router.put(`${base}/${entry.id}`, {
        match_type: entry.match_type,
        value: entry.value,
        is_regex: entry.is_regex,
        is_enabled: !entry.is_enabled,
        notes: entry.notes ?? '',
    }, options);
};

const remove = (entry) => router.delete(`${base}/${entry.id}`, options);
</script>

<template>
    <Head :title="`Blocklist - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Blocklist</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <p v-if="successMessage" role="status" data-testid="blocklist-success" class="rounded border border-border bg-surface px-5 py-3 text-sm">{{ successMessage }}</p>

        <section class="rounded border border-border bg-surface px-5 py-5">
            <p v-if="entries.length === 0" data-testid="blocklist-empty" class="text-sm text-muted">No blocklist entries yet.</p>
            <div v-else class="overflow-x-auto">
                <table data-testid="blocklist-table" class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-muted">
                            <th scope="col" class="py-2 pr-3 font-medium">Type</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Value</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Enabled</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Notes</th>
                            <th v-if="abilities.canManage" scope="col" class="py-2 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="entry in entries" :key="entry.id" :data-testid="`blocklist-entry-${entry.id}`">
                            <td class="py-3 pr-3">{{ typeLabel(entry.match_type) }}</td>
                            <td class="py-3 pr-3">
                                <span class="break-all">{{ entry.value }}</span>
                                <span
                                    v-if="entry.is_regex"
                                    data-testid="blocklist-regex-badge"
                                    class="ml-2 rounded border border-border px-2 py-0.5 text-xs font-medium"
                                >Regex</span>
                            </td>
                            <td class="py-3 pr-3">
                                <label class="flex min-h-11 items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :checked="entry.is_enabled"
                                        :disabled="busy || !abilities.canManage"
                                        :data-testid="`blocklist-toggle-${entry.id}`"
                                        @change="toggle(entry)"
                                    >
                                    <span class="sr-only">Enabled for {{ entry.value }}</span>
                                </label>
                            </td>
                            <td class="py-3 pr-3 text-muted">{{ entry.notes }}</td>
                            <td v-if="abilities.canManage" class="py-3">
                                <button
                                    type="button"
                                    :disabled="busy"
                                    :data-testid="`blocklist-delete-${entry.id}`"
                                    class="min-h-11 rounded border border-red-500 px-3 text-sm text-red-500 disabled:opacity-50"
                                    @click="remove(entry)"
                                >Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <form
            v-if="abilities.canManage"
            data-testid="blocklist-add-form"
            class="flex flex-col gap-4 rounded border border-border bg-surface px-5 py-5"
            @submit.prevent="submit"
        >
            <h2 class="text-base font-bold">Add entry</h2>

            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium">Match type</span>
                <select
                    v-model="form.match_type"
                    data-testid="blocklist-type-select"
                    class="min-h-11 rounded border border-border bg-surface px-2 text-sm"
                >
                    <option v-for="type in matchTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
                <span v-if="form.errors.match_type" role="alert" data-testid="error-match_type" class="text-xs text-red-500">{{ form.errors.match_type }}</span>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium">Value</span>
                <input
                    v-model="form.value"
                    type="text"
                    data-testid="blocklist-value-input"
                    class="min-h-11 rounded border border-border bg-surface px-2 text-sm"
                >
                <span v-if="form.errors.value" role="alert" data-testid="error-value" class="text-xs text-red-500">{{ form.errors.value }}</span>
            </label>

            <div class="flex flex-wrap gap-4">
                <label v-if="supportsRegex" class="flex min-h-11 items-center gap-2 text-sm">
                    <input v-model="form.is_regex" type="checkbox" data-testid="blocklist-regex-checkbox">
                    <span>Regex</span>
                </label>
                <label class="flex min-h-11 items-center gap-2 text-sm">
                    <input v-model="form.is_enabled" type="checkbox" data-testid="blocklist-enabled-checkbox">
                    <span>Enabled</span>
                </label>
            </div>
            <span v-if="form.errors.is_regex" role="alert" data-testid="error-is_regex" class="text-xs text-red-500">{{ form.errors.is_regex }}</span>
            <span v-if="form.errors.is_enabled" role="alert" data-testid="error-is_enabled" class="text-xs text-red-500">{{ form.errors.is_enabled }}</span>

            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium">Notes</span>
                <textarea
                    v-model="form.notes"
                    rows="3"
                    data-testid="blocklist-notes-input"
                    class="rounded border border-border bg-surface px-2 py-2 text-sm"
                />
                <span v-if="form.errors.notes" role="alert" data-testid="error-notes" class="text-xs text-red-500">{{ form.errors.notes }}</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                data-testid="blocklist-submit"
                class="min-h-11 self-start rounded bg-accent px-4 text-sm font-medium text-white disabled:opacity-50"
            >Add entry</button>
        </form>
    </div>
</template>

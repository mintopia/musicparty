<script setup>
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';

const props = defineProps({
    party: {type: Object, required: true},
    mods: {type: Array, required: true},
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.successMessage ?? null);
const base = `/parties/${props.party.code}/mods`;

const drafts = reactive({});
const errors = ref({});
const busy = ref(false);

const draftFor = (mod) => {
    drafts[mod.id] ??= {...mod.settings};

    return drafts[mod.id];
};

const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
};

const toggle = (mod) => {
    errors.value = {};
    if (mod.enabled) {
        router.delete(`${base}/${mod.id}`, {...options, onSuccess: () => delete drafts[mod.id]});
    } else {
        router.put(`${base}/${mod.id}`, {}, {...options, onSuccess: () => delete drafts[mod.id]});
    }
};

const save = (mod) => {
    errors.value = {};
    router.put(`${base}/${mod.id}/settings`, {settings: draftFor(mod)}, {
        ...options,
        onError: (bag) => {
            errors.value = bag;
        },
    });
};
</script>

<template>
    <Head :title="`Mods - ${party.name}`" />
    <div class="flex flex-col gap-4 px-4 pb-4 pt-6">
        <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-border bg-surface px-5 py-5">
            <h1 class="text-xl font-semibold">Mods</h1>
            <Link
                :href="`/parties/${party.code}`"
                class="flex min-h-11 items-center rounded border border-border px-3 text-sm hover:text-primary"
            >Back to {{ party.name }}</Link>
        </header>

        <p v-if="successMessage" role="status" data-testid="mods-success" class="rounded border border-border bg-surface px-5 py-3 text-sm">{{ successMessage }}</p>
        <p v-if="mods.length === 0" data-testid="mods-empty" class="rounded border border-border bg-surface px-5 py-5 text-sm text-muted">No Mods are available.</p>

        <section
            v-for="mod in mods"
            :key="mod.id"
            :data-testid="`mod-${mod.id}`"
            class="flex flex-col gap-4 rounded border border-border bg-surface px-5 py-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold">{{ mod.name }}</h2>
                    <p class="text-sm text-muted">{{ mod.description }}</p>
                </div>
                <button
                    type="button"
                    :disabled="busy"
                    :data-testid="`mod-toggle-${mod.id}`"
                    class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                    @click="toggle(mod)"
                >{{ mod.enabled ? 'Disable' : 'Enable' }}</button>
            </div>

            <form v-if="mod.enabled && mod.definitions.length" class="flex flex-col gap-4" @submit.prevent="save(mod)">
                <template v-for="definition in mod.definitions" :key="definition.key">
                    <label v-if="definition.kind === 'boolean'" class="flex min-h-11 items-center gap-3 text-sm">
                        <input v-model="draftFor(mod)[definition.key]" type="checkbox" :data-testid="`mod-setting-${mod.id}-${definition.key}`">
                        <span>{{ definition.label }}</span>
                    </label>
                    <label v-else class="flex flex-col gap-1 text-sm">
                        <span class="font-medium">{{ definition.label }}</span>
                        <select
                            v-if="definition.kind === 'choice'"
                            v-model="draftFor(mod)[definition.key]"
                            :data-testid="`mod-setting-${mod.id}-${definition.key}`"
                            class="min-h-11 rounded border border-border bg-surface px-2"
                        >
                            <option v-for="option in definition.options" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <input
                            v-else
                            v-model="draftFor(mod)[definition.key]"
                            :type="definition.kind === 'integer' ? 'number' : definition.kind === 'secret' ? 'password' : 'text'"
                            :min="definition.min ?? undefined"
                            :max="definition.max ?? undefined"
                            :autocomplete="definition.kind === 'secret' ? 'off' : undefined"
                            :data-testid="`mod-setting-${mod.id}-${definition.key}`"
                            class="min-h-11 rounded border border-border bg-surface px-2"
                        >
                        <span v-if="definition.kind === 'secret'" class="text-xs text-muted">Stored encrypted. Leave unchanged to keep the current value.</span>
                    </label>
                    <span v-if="errors[definition.key]" role="alert" :data-testid="`mod-error-${mod.id}-${definition.key}`" class="-mt-3 text-xs text-red-500">{{ errors[definition.key] }}</span>
                </template>
                <span v-if="errors.mod" role="alert" class="text-xs text-red-500">{{ errors.mod }}</span>
                <div>
                    <button
                        type="submit"
                        :disabled="busy"
                        :data-testid="`mod-save-${mod.id}`"
                        class="min-h-11 rounded border border-border px-4 text-sm hover:text-primary disabled:opacity-50"
                    >Save settings</button>
                </div>
            </form>
        </section>
    </div>
</template>
